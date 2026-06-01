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
import type { BreadcrumbItem, Dentist } from '@/types'
import { WORK_TYPES } from '@/types'
import { useTranslation } from '@/lib/translations'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('orders.title'),
		href: '/orders',
	},
	{
		title: t('orders.add_new'),
		href: '/orders/create',
	},
]

type OrderItem = {
	type: string
	quantity: number
	price: number
}

export default function OrdersCreate({ dentists }: { dentists: Dentist[] }) {
	const { t } = useTranslation()
	const { data, setData, post, processing, errors } = useForm({
		dentist_id: '',
		due_date: '',
		amount: '',
		notes: '',
		items: [] as OrderItem[],
	})

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		post('/orders')
	}

	const addItem = () => {
		setData('items', [...data.items, { type: WORK_TYPES[0], quantity: 1, price: 0 }])
	}

	const removeItem = (index: number) => {
		setData(
			'items',
			data.items.filter((_, i) => i !== index)
		)
	}

	const updateItem = (index: number, field: string, value: any) => {
		const newItems = [...data.items]
		newItems[index] = { ...newItems[index], [field]: value }
		setData('items', newItems)

		if (field === 'quantity' || field === 'price') {
			const total = newItems.reduce((sum, item) => sum + item.quantity * item.price, 0)
			setData('amount', total.toString())
		}
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('orders.add')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<Button variant="ghost" size="sm" className="w-fit" onClick={() => window.history.back()}>
					<ArrowRight className="h-4 w-4" />
					{t('action.back')}
				</Button>

				<Heading variant="small" title={t('orders.add_new')} />

				<form onSubmit={handleSubmit} className="max-w-4xl space-y-8">
					<div className="grid gap-6 md:grid-cols-2">
						<div className="space-y-6">
							<div className="grid gap-2">
								<Label htmlFor="dentist_id">{t('order.dentist')}</Label>
								<Select
									value={data.dentist_id}
									onValueChange={(value) => setData('dentist_id', value)}
								>
									<SelectTrigger>
										<SelectValue placeholder={t('order.select_dentist')} />
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
								<Label htmlFor="due_date">
									{t('order.due_date')} <span className="text-muted-foreground">{t('common.optional')}</span>
								</Label>
								<Input
									id="due_date"
									type="date"
									value={data.due_date}
									onChange={(e) => setData('due_date', e.target.value)}
								/>
								<InputError message={errors.due_date} />
							</div>

							<div className="grid gap-2">
								<Label htmlFor="amount">{t('order.amount')}</Label>
								<Input
									id="amount"
									type="number"
									min="0"
									value={data.amount}
									onChange={(e) => setData('amount', e.target.value)}
									required
								/>
								<InputError message={errors.amount} />
							</div>

							<div className="grid gap-2">
								<Label htmlFor="notes">
									{t('order.notes')} <span className="text-muted-foreground">{t('common.optional')}</span>
								</Label>
								<Textarea
									id="notes"
									value={data.notes}
									onChange={(e) => setData('notes', e.target.value)}
									rows={3}
								/>
								<InputError message={errors.notes} />
							</div>
						</div>

						<div className="space-y-4 rounded-lg border p-4">
							<div className="flex items-center justify-between">
								<Label className="text-base font-semibold">{t('order.items')}</Label>
								<Button type="button" variant="outline" size="sm" onClick={addItem}>
									<Plus className="mr-2 h-4 w-4" />
									{t('order.add_item')}
								</Button>
							</div>

							{data.items.length === 0 ? (
								<p className="text-sm text-muted-foreground text-center py-8">
									{t('order.no_items')}
								</p>
							) : (
								<div className="space-y-4">
									{data.items.map((item, index) => (
										<div key={index} className="flex gap-2 items-start rounded-md border p-3">
											<div className="grid flex-1 gap-4 sm:grid-cols-3">
												<div className="grid gap-2">
													<Label className="text-xs">{t('order.item_type')}</Label>
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
													<Label className="text-xs">{t('order.item_quantity')}</Label>
													<Input
														type="number"
														min="1"
														value={item.quantity}
														onChange={(e) =>
															updateItem(index, 'quantity', parseInt(e.target.value) || 0)
														}
													/>
												</div>
												<div className="grid gap-2">
													<Label className="text-xs">{t('order.item_price')}</Label>
													<Input
														type="number"
														min="0"
														value={item.price}
														onChange={(e) =>
															updateItem(index, 'price', parseInt(e.target.value) || 0)
														}
													/>
												</div>
											</div>
											<Button
												type="button"
												variant="ghost"
												size="icon"
												className="mt-6 text-destructive"
												onClick={() => removeItem(index)}
											>
												<Trash2 className="h-4 w-4" />
											</Button>
										</div>
					</div>

					<div className="rounded-lg border bg-muted/50 p-4">
						<p className="text-lg font-semibold">
							{t('common.total')}: {total.toLocaleString('en-US')}
						</p>
					</div>

					<Button type="submit" disabled={processing || data.items.length === 0}>
						حفظ
					</Button>
				</form>
			</div>
		</AppLayout>
	)
}
