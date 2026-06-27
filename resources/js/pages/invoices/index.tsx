import { Head, router, useForm } from '@inertiajs/react'
import { Printer } from 'lucide-react'
import Heading from '@/components/heading'
import { Dash, formatDate, itemAmount, itemDate, itemPatient, itemTeeth, TeethBadges } from '@/components/order-display'
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
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Dentist, DentistPayment, Order, OrderItem } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'الفواتير',
		href: '/invoices',
	},
]

type DentistGroup = {
	id: number
	name: string
	rows: { order: Order; item: OrderItem | null }[]
	total: number
}

/** Group all order item rows by dentist, with a per-dentist total. */
function groupByDentist(orders: Order[]): DentistGroup[] {
	const map = new Map<number, DentistGroup>()
	for (const order of orders) {
		const group =
			map.get(order.dentist_id) ??
			{ id: order.dentist_id, name: order.dentist?.name ?? '—', rows: [], total: 0 }
		const items = order.items ?? []
		if (items.length === 0) {
			group.rows.push({ order, item: null })
			group.total += order.amount
		} else {
			for (const item of items) {
				group.rows.push({ order, item })
				group.total += itemAmount(item)
			}
		}
		map.set(order.dentist_id, group)
	}
	return [...map.values()]
}

type InvoiceData = {
	orders: Order[] | null
	payments: DentistPayment[] | null
	totals: {
		orders: number
		payments: number
		balance: number
	} | null
	dentists: Dentist[]
	filters: {
		from: string | null
		to: string | null
		dentist_id: string | null
	}
}

export default function InvoicesIndex({
	orders,
	payments,
	totals,
	dentists,
	filters,
}: InvoiceData) {
	const { data, setData } = useForm({
		from: filters.from || '',
		to: filters.to || '',
		dentist_id: filters.dentist_id || '',
	})

	const handleView = (e: React.FormEvent) => {
		e.preventDefault()
		router.get('/invoices', data as Record<string, string>)
	}

	const handlePrint = () => {
		window.print()
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="الفواتير" />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<h1 className="text-2xl font-bold">الفواتير</h1>

				{/* Filter Form - Hidden on print */}
				<form onSubmit={handleView} className="print:hidden space-y-4 rounded-lg border p-4">
					<Heading variant="small" title="تصفية البيانات" />

					<div className="grid gap-4 sm:grid-cols-3">
						<div className="grid gap-2">
							<Label htmlFor="from">من تاريخ</Label>
							<Input
								id="from"
								type="date"
								value={data.from}
								onChange={(e) => setData('from', e.target.value)}
								required
							/>
						</div>

						<div className="grid gap-2">
							<Label htmlFor="to">إلى تاريخ</Label>
							<Input
								id="to"
								type="date"
								value={data.to}
								onChange={(e) => setData('to', e.target.value)}
								required
							/>
						</div>

						<div className="grid gap-2">
							<Label htmlFor="dentist_id">الطبيب (اختياري)</Label>
							<div className="flex gap-2">
								<Select
									value={data.dentist_id || undefined}
									onValueChange={(value) => setData('dentist_id', value)}
								>
									<SelectTrigger>
										<SelectValue placeholder="الكل" />
									</SelectTrigger>
									<SelectContent>
										{dentists.map((dentist) => (
											<SelectItem key={dentist.id} value={dentist.id.toString()}>
												{dentist.name}
											</SelectItem>
										))}
									</SelectContent>
								</Select>
								{data.dentist_id && (
									<Button
										type="button"
										variant="outline"
										size="sm"
										onClick={() => setData('dentist_id', '')}
									>
										مسح
									</Button>
								)}
							</div>
						</div>
					</div>

					<div className="flex gap-2">
						<Button type="submit">عرض</Button>
						{orders && (
							<Button type="button" onClick={handlePrint} variant="outline">
								<Printer className="h-4 w-4" />
								طباعة
							</Button>
						)}
					</div>
				</form>

				{/* Printable Report */}
				{orders && payments && totals && (
					<div className="space-y-6">
						{/* Header - visible on print */}
						<div className="text-center">
							<h2 className="text-xl font-bold">تقرير الفواتير</h2>
							<p className="text-sm text-muted-foreground">
								من {new Date(filters.from!).toLocaleDateString('en-US')} إلى{' '}
								{new Date(filters.to!).toLocaleDateString('en-US')}
							</p>
						</div>

						{/* Orders grouped by dentist */}
						<div className="space-y-4">
							<h3 className="text-lg font-semibold">الطلبات</h3>
							{orders.length === 0 ? (
								<div className="rounded-lg border p-6 text-center text-sm text-muted-foreground">
									لا توجد طلبات
								</div>
							) : (
								groupByDentist(orders).map((group) => (
									<div key={group.id} className="space-y-2">
										<div className="text-center">
											<h4 className="text-2xl font-bold">
												الطبيب المحترم : {group.name}
											</h4>
										</div>
										<div className="rounded-lg border">
											<Table>
												<TableHeader>
													<TableRow>
														<TableHead>اسم المريض</TableHead>
														<TableHead>التاريخ</TableHead>
														<TableHead>العنصر</TableHead>
														<TableHead>الأسنان</TableHead>
														<TableHead>المبلغ</TableHead>
													</TableRow>
												</TableHeader>
												<TableBody>
													{group.rows.map(({ order, item }) =>
														item ? (
															<TableRow key={`i-${item.id}`}>
																<TableCell>{itemPatient(item) || <Dash />}</TableCell>
																<TableCell className="whitespace-nowrap">
																	{formatDate(itemDate(item)) || formatDate(order.due_date) || <Dash />}
																</TableCell>
																<TableCell className="whitespace-nowrap">
																	{item.type}{' '}
																	<span className="text-muted-foreground">× {item.quantity}</span>
																</TableCell>
																<TableCell>
																	<TeethBadges teeth={itemTeeth(item)} />
																</TableCell>
																<TableCell className="tabular-nums">
																	{itemAmount(item).toLocaleString('en-US')}
																</TableCell>
															</TableRow>
														) : (
															<TableRow key={`o-${order.id}`}>
																<TableCell><Dash /></TableCell>
																<TableCell className="whitespace-nowrap">
																	{formatDate(order.due_date) || <Dash />}
																</TableCell>
																<TableCell><Dash /></TableCell>
																<TableCell><Dash /></TableCell>
																<TableCell className="tabular-nums">
																	{order.amount.toLocaleString('en-US')}
																</TableCell>
															</TableRow>
														)
													)}
												</TableBody>
											</Table>
										</div>
										<div className="flex items-center justify-between rounded-md bg-muted px-3 py-2 text-sm font-semibold">
											<span>إجمالي مبلغ الطلبات</span>
											<span className="tabular-nums">{group.total.toLocaleString('en-US')}</span>
										</div>
									</div>
								))
							)}
						</div>

						{/* Payments Table */}
						<div className="space-y-2">
							<h3 className="text-lg font-semibold">المدفوعات</h3>
							<div className="rounded-lg border">
								<Table>
									<TableHeader>
										<TableRow>
											<TableHead>الطبيب</TableHead>
											<TableHead>التاريخ</TableHead>
											<TableHead>المبلغ</TableHead>
										</TableRow>
									</TableHeader>
									<TableBody>
										{payments.length === 0 ? (
											<TableRow>
												<TableCell colSpan={3} className="text-center">
													لا توجد مدفوعات
												</TableCell>
											</TableRow>
										) : (
											payments.map((payment) => (
												<TableRow key={payment.id}>
													<TableCell>{payment.dentist?.name}</TableCell>
													<TableCell>
														{new Date(payment.payment_date || payment.created_at).toLocaleDateString(
															'en-US'
														)}
													</TableCell>
													<TableCell>
														{payment.amount.toLocaleString('en-US')}
													</TableCell>
												</TableRow>
											))
										)}
									</TableBody>
								</Table>
							</div>
						</div>

						{/* Summary */}
						<div className="rounded-lg border bg-muted/50 p-4 space-y-2">
							<h3 className="text-lg font-semibold">الملخص</h3>
							<div className="grid gap-2">
								<div className="flex justify-between">
									<span>إجمالي الطلبات:</span>
									<span className="font-semibold">
										{totals.orders.toLocaleString('en-US')}
									</span>
								</div>
								<div className="flex justify-between">
									<span>إجمالي المدفوعات:</span>
									<span className="font-semibold">
										{totals.payments.toLocaleString('en-US')}
									</span>
								</div>
								<div className="flex justify-between border-t pt-2">
									<span className="font-bold">الرصيد المتبقي:</span>
									<span className="font-bold text-lg">
										{totals.balance.toLocaleString('en-US')}
									</span>
								</div>
							</div>
						</div>
					</div>
				)}
			</div>
		</AppLayout>
	)
}
