import { Head, useForm } from '@inertiajs/react'
import { ArrowRight, Plus, Trash2 } from 'lucide-react'
import { useCallback } from 'react'
import DentalChart from '@/components/dental-chart'
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
import { ORDER_STATUSES } from '@/types'

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
	patient_name: string
	quantity: number
	price: number
	notes: string
	date: string
	selected_teeth: number[]
}

const today = () => new Date().toISOString().slice(0, 10)

export default function OrdersEdit({ order, dentists }: { order: Order; dentists: Dentist[] }) {
	const { data, setData, put, processing, errors } = useForm({
		dentist_id: order.dentist_id.toString(),
		status: order.status,
		notes: order.notes || '',
		items: (order.items || []).map((item) => {
			const meta = item.meta as Record<string, unknown> | null
			return {
				type: item.type,
				patient_name: (meta?.patient_name as string) || '',
				quantity: item.quantity,
				price: item.price,
				notes: item.notes || '',
				date: (meta?.date as string) || today(),
				selected_teeth: (meta?.selected_teeth as number[]) || [],
			}
		}) as OrderItem[],
	})

	const getSelectedDentist = useCallback(() => {
		return dentists.find((d) => d.id.toString() === data.dentist_id)
	}, [dentists, data.dentist_id])

	// Available work types come from the selected dentist's own price list.
	const workTypeNames = data.dentist_id
		? Object.keys(getSelectedDentist()?.price_list ?? {})
		: []

	const getDentistPrice = useCallback(
		(type: string): number | null => {
			const dentist = getSelectedDentist()
			if (dentist?.price_list && type in dentist.price_list) {
				return dentist.price_list[type]
			}
			return null
		},
		[getSelectedDentist]
	)

	const handleDentistChange = (value: string) => {
		setData((prev) => {
			const dentist = dentists.find((d) => d.id.toString() === value)
			const updatedItems = prev.items.map((item) => {
				if (dentist?.price_list && item.type in dentist.price_list) {
					return { ...item, price: dentist.price_list[item.type] }
				}
				return item
			})
			return { ...prev, dentist_id: value, items: updatedItems }
		})
	}

	const addItem = () => {
		const defaultType = workTypeNames[0] || ''
		const price = getDentistPrice(defaultType) ?? 0
		setData('items', [
			...data.items,
			{ type: defaultType, patient_name: '', quantity: 1, price, notes: '', date: today(), selected_teeth: [] },
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

		// Auto-fill price when work type changes
		if (field === 'type') {
			const dentistPrice = getDentistPrice(value as string)
			if (dentistPrice !== null) {
				newItems[index].price = dentistPrice
			}
		}

		setData('items', newItems)
	}

	const renderTypeInput = (item: OrderItem, index: number) => {
		const isCustom = item.type !== '' && !workTypeNames.includes(item.type) && item.type !== 'أخرى'

		if (isCustom || item.type === 'أخرى') {
			return (
				<div className="flex gap-2 items-center">
					<Input
						value={item.type === 'أخرى' ? '' : item.type}
						onChange={(e) => updateItem(index, 'type', e.target.value)}
						placeholder="أدخل النوع..."
						autoFocus
					/>
					<Button
						type="button"
						variant="ghost"
						size="sm"
						onClick={() => updateItem(index, 'type', '')}
						title="العودة للقائمة"
					>
						إلغاء
					</Button>
				</div>
			)
		}

		return (
			<Select
				value={item.type}
				onValueChange={(value) => updateItem(index, 'type', value)}
			>
				<SelectTrigger>
					<SelectValue placeholder="اختر النوع" />
				</SelectTrigger>
				<SelectContent>
					{workTypeNames.map((type) => (
						<SelectItem key={type} value={type}>
							{type}
						</SelectItem>
					))}
					<SelectItem value="أخرى">أخرى (كتابة)</SelectItem>
				</SelectContent>
			</Select>
		)
	}

	const total = data.items.reduce((sum, item) => sum + item.quantity * item.price, 0)

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		put(`/orders/${order.id}`)
	}

	const selectedDentist = getSelectedDentist()

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
							onValueChange={handleDentistChange}
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
						<Label htmlFor="status">الحالة</Label>
						<Select value={data.status} onValueChange={(value: string) => setData('status', value as typeof data.status)}>
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

						{selectedDentist?.price_list && Object.keys(selectedDentist.price_list).length > 0 && (
							<p className="text-sm text-muted-foreground">
								💡 يتم ملء الأسعار تلقائياً حسب قائمة أسعار الطبيب <span className="font-medium">{selectedDentist.name}</span>
							</p>
						)}

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
												{renderTypeInput(item, index)}
											</div>

											<div className="grid gap-2">
												<Label>اسم المريض</Label>
												<Input
													value={item.patient_name}
													onChange={(e) => updateItem(index, 'patient_name', e.target.value)}
													placeholder="أدخل اسم المريض..."
												/>
											</div>

											<div className="grid gap-2">
												<Label>التاريخ</Label>
												<Input
													type="date"
													value={item.date}
													onChange={(e) => updateItem(index, 'date', e.target.value)}
													required
												/>
											</div>

											<div className="grid gap-2">
												<Label>الكمية {item.selected_teeth.length > 0 && <span className="text-xs text-muted-foreground font-normal">(حسب الأسنان المختارة)</span>}</Label>
												<Input
													type="number"
													min="1"
													value={item.quantity}
													onChange={(e) =>
														updateItem(index, 'quantity', parseInt(e.target.value))
													}
													readOnly={item.selected_teeth.length > 0}
													className={item.selected_teeth.length > 0 ? 'bg-muted' : ''}
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

										<DentalChart
											selectedTeeth={item.selected_teeth}
											onSelectionChange={(teeth) => {
												const newItems = [...data.items]
												newItems[index] = {
													...newItems[index],
													selected_teeth: teeth,
													quantity: teeth.length > 0 ? teeth.length : newItems[index].quantity,
												}
												setData('items', newItems)
											}}
										/>

										<p className="text-sm text-muted-foreground">
											المجموع الفرعي: {(item.quantity * item.price).toLocaleString('en-US')}
										</p>
									</div>
								))}
							</div>
						)}
						<InputError message={errors.items} />
					</div>

					<div className="rounded-lg border bg-muted/50 p-4">
						<p className="text-lg font-semibold">
							المجموع الكلي: {total.toLocaleString('en-US')}
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
