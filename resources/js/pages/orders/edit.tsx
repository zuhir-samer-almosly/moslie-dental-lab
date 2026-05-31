import { Head, useForm } from '@inertiajs/react'
import { ArrowRight, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import Heading from '@/components/heading'
import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Dentist, Order } from '@/types'
import { ORDER_STATUSES, WORK_TYPES } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'الطلبات',
		href: '/orders',
	},
	{
		title: 'تعديل طلب',
		href: '#',
	},
]

type OrderItem = {
	type: string
	quantity: number
	price: number
	notes: string
}

export default function OrdersEdit({ order, dentists }: { order: Order; dentists: Dentist[] }) {
	const { data, setData, put, processing, errors } = useForm({
		dentist_id: order.dentist_id.toString(),
		due_date: order.due_date,
		status: order.status,
		notes: order.notes || '',
		items: (order.items || []).map((item) => ({
			type: item.type,
			quantity: item.quantity,
			price: item.price,
			notes: item.notes || '',
		})) as OrderItem[],
	})

	const addItem = () => {
		setData('items', [
			...data.items,
			{ type: WORK_TYPES[0], quantity: 1, price: 0, notes: '' },
		])
	}

	const removeItem = (index: number) => {
		setData(
			'items',
			data.items.filter((_, i) => i !== index)
		)
	}

	const updateItem = (index: number, field: keyof OrderItem, value: string | number) => {
		const newItems = [...data.items]
		newItems[index] = { ...newItems[index], [field]: value }
		setData('items', newItems)
	}

	const total = data.items.reduce((sum, item) => sum + item.quantity * item.price, 0)

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		put(`/orders/${order.id}`)
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="تعديل طلب" />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<Button variant="ghost" size="sm" className="w-fit" onClick={() => window.history.back()}>
					<ArrowRight className="h-4 w-4" />
					رجوع
				</Button>

				<Heading variant="small" title="تعديل طلب" />

				<form onSubmit={handleSubmit} className="max-w-4xl space-y-6">
					<div className="grid gap-2">
						<Label htmlFor="dentist_id">الطبيب</Label>
						<Select
							value={data.dentist_id}
							onValueChange={(value) => setData('dentist_id', value)}
						>
							<SelectTrigger>
								<SelectValue placeholder="اختر الطبيب" />
							</SelectTrigger>
							<SelectContent>
								{dentists.map((dentist) => (
									<SelectItem key={dentist.id} value={dentist.id.toString()}>
										{dentist.name}
									</SelectItem>
								))}
							</SelectContent>
						</Select>
						<InputError message={errors.dentist_id} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="due_date">تاريخ الاستحقاق</Label>
						<Input
							id="due_date"
							type="date"
							value={data.due_date}
							onChange={(e) => setData('due_date', e.target.value)}
							required
						/>
						<InputError message={errors.due_date} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="status">الحالة</Label>
						<Select value={data.status} onValueChange={(value: any) => setData('status', value)}>
							<SelectTrigger>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								{Object.entries(ORDER_STATUSES).map(([key, label]) => (
									<SelectItem key={key} value={key}>
										{label}
									</SelectItem>
								))}
							</SelectContent>
						</Select>
						<InputError message={errors.status} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="notes">ملاحظات</Label>
						<Textarea
							id="notes"
							value={data.notes}
							onChange={(e) => setData('notes', e.target.value)}
							rows={3}
						/>
						<InputError message={errors.notes} />
					</div>

					<div className="space-y-4">
						<div className="flex items-center justify-between">
							<Label>العناصر</Label>
							<Button type="button" onClick={addItem} variant="outline" size="sm">
								<Plus className="h-4 w-4" />
								إضافة عنصر
							</Button>
						</div>

						{data.items.length === 0 ? (
							<p className="text-sm text-muted-foreground">لا توجد عناصر. قم بإضافة عنصر واحد على الأقل.</p>
						) : (
							<div className="space-y-4">
								{data.items.map((item, index) => (
									<div key={index} className="rounded-lg border p-4 space-y-4">
										<div className="flex items-start justify-between">
											<h4 className="font-medium">عنصر {index + 1}</h4>
											<Button
												type="button"
												variant="ghost"
												size="sm"
												onClick={() => removeItem(index)}
											>
												<Trash2 className="h-4 w-4" />
											</Button>
										</div>

										<div className="grid gap-4 sm:grid-cols-2">
											<div className="grid gap-2">
												<Label>النوع</Label>
												<Select
													value={item.type}
													onValueChange={(value) => updateItem(index, 'type', value)}
												>
													<SelectTrigger>
														<SelectValue />
													</SelectTrigger>
													<SelectContent>
														{WORK_TYPES.map((type) => (
															<SelectItem key={type} value={type}>
																{type}
															</SelectItem>
														))}
													</SelectContent>
												</Select>
											</div>

											<div className="grid gap-2">
												<Label>الكمية</Label>
												<Input
													type="number"
													min="1"
													value={item.quantity}
													onChange={(e) =>
														updateItem(index, 'quantity', parseInt(e.target.value))
													}
												/>
											</div>

											<div className="grid gap-2">
												<Label>السعر</Label>
												<Input
													type="number"
													min="0"
													value={item.price}
													onChange={(e) =>
														updateItem(index, 'price', parseInt(e.target.value))
													}
												/>
											</div>

											<div className="grid gap-2">
												<Label>ملاحظات</Label>
												<Input
													value={item.notes}
													onChange={(e) => updateItem(index, 'notes', e.target.value)}
												/>
											</div>
										</div>

										<p className="text-sm text-muted-foreground">
											المجموع الفرعي: {(item.quantity * item.price).toLocaleString('ar-SY')}
										</p>
									</div>
								))}
							</div>
						)}
						<InputError message={errors.items} />
					</div>

					<div className="rounded-lg border bg-muted/50 p-4">
						<p className="text-lg font-semibold">
							المجموع الكلي: {total.toLocaleString('ar-SY')}
						</p>
					</div>

					<Button type="submit" disabled={processing || data.items.length === 0}>
						تحديث
					</Button>
				</form>
			</div>
		</AppLayout>
	)
}
