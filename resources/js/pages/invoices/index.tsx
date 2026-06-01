import { Head, router, useForm } from '@inertiajs/react'
import { Printer } from 'lucide-react'
import Heading from '@/components/heading'
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
import type { BreadcrumbItem, Dentist, DentistPayment, Order } from '@/types'
import { useTranslation } from '@/lib/translations'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('invoices.title'),
		href: '/invoices',
	},
]

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
	const { t, language } = useTranslation()
	const locale = 'en-US';
	const { data, setData } = useForm({
		from: filters.from || '',
		to: filters.to || '',
		dentist_id: filters.dentist_id || '',
	})

	const handleView = (e: React.FormEvent) => {
		e.preventDefault()
		router.get('/invoices', data as any)
	}

	const handlePrint = () => {
		window.print()
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('invoices.title')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<h1 className="text-2xl font-bold">{t('invoices.title')}</h1>

				{/* Filter Form - Hidden on print */}
				<form onSubmit={handleView} className="print:hidden space-y-4 rounded-lg border p-4">
					<Heading variant="small" title={t('invoices.filter_title')} />

					<div className="grid gap-4 sm:grid-cols-3">
						<div className="grid gap-2">
							<Label htmlFor="from">{t('invoices.from_date')}</Label>
							<Input
								id="from"
								type="date"
								value={data.from}
								onChange={(e) => setData('from', e.target.value)}
								required
							/>
						</div>

						<div className="grid gap-2">
							<Label htmlFor="to">{t('invoices.to_date')}</Label>
							<Input
								id="to"
								type="date"
								value={data.to}
								onChange={(e) => setData('to', e.target.value)}
								required
							/>
						</div>

						<div className="grid gap-2">
							<Label htmlFor="dentist_id">{t('order.dentist')} {t('common.optional')}</Label>
							<div className="flex gap-2">
								<Select
									value={data.dentist_id || undefined}
									onValueChange={(value) => setData('dentist_id', value)}
								>
									<SelectTrigger>
										<SelectValue placeholder={t('common.all')} />
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
										{t('action.clear')}
									</Button>
								)}
							</div>
						</div>
					</div>

					<div className="flex gap-2">
						<Button type="submit">{t('action.show')}</Button>
						{orders && (
							<Button type="button" onClick={handlePrint} variant="outline">
								<Printer className="h-4 w-4" />
								{t('action.print')}
							</Button>
						)}
					</div>
				</form>

				{/* Printable Report */}
				{orders && payments && totals && (
					<div className="space-y-6">
						{/* Header - visible on print */}
						<div className="text-center">
							<h2 className="text-xl font-bold">{t('invoices.report_title')}</h2>
							<p className="text-sm text-muted-foreground">
								{new Date(filters.from!).toLocaleDateString(locale)} -{' '}
								{new Date(filters.to!).toLocaleDateString(locale)}
							</p>
							{filters.dentist_id && (
								<p className="text-sm text-muted-foreground">
									{t('order.dentist')}:{' '}
									{dentists.find((d) => d.id.toString() === filters.dentist_id)?.name}
								</p>
							)}
						</div>

						{/* Orders Table */}
						<div className="space-y-2">
							<h3 className="text-lg font-semibold">{t('orders.title')}</h3>
							<div className="rounded-lg border">
								<Table>
									<TableHeader>
										<TableRow>
											<TableHead>{t('order.dentist_name')}</TableHead>
											<TableHead>{t('payment.date')}</TableHead>
											<TableHead>{t('order.items')}</TableHead>
											<TableHead>{t('order.amount')}</TableHead>
										</TableRow>
									</TableHeader>
									<TableBody>
										{orders.length === 0 ? (
											<TableRow>
												<TableCell colSpan={4} className="text-center">
													{t('common.no_data')}
												</TableCell>
											</TableRow>
										) : (
											orders.map((order) => (
												<TableRow key={order.id}>
													<TableCell>{order.dentist?.name}</TableCell>
													<TableCell>
														{new Date(order.created_at).toLocaleDateString(
															locale
														)}
													</TableCell>
													<TableCell>
														<ul className="text-sm">
															{order.items?.map((item, idx) => (
																<li key={idx}>
																	{item.type} × {item.quantity}
																</li>
															))}
														</ul>
													</TableCell>
													<TableCell>
														{order.amount.toLocaleString(locale)}
													</TableCell>
												</TableRow>
											))
										)}
									</TableBody>
								</Table>
							</div>
						</div>

						{/* Payments Table */}
						<div className="space-y-2">
							<h3 className="text-lg font-semibold">{t('payments.title')}</h3>
							<div className="rounded-lg border">
								<Table>
									<TableHeader>
										<TableRow>
											<TableHead>{t('order.dentist_name')}</TableHead>
											<TableHead>{t('payment.date')}</TableHead>
											<TableHead>{t('payment.amount')}</TableHead>
										</TableRow>
									</TableHeader>
									<TableBody>
										{payments.length === 0 ? (
											<TableRow>
												<TableCell colSpan={3} className="text-center">
													{t('common.no_data')}
												</TableCell>
											</TableRow>
										) : (
											payments.map((payment) => (
												<TableRow key={payment.id}>
													<TableCell>{payment.dentist?.name}</TableCell>
													<TableCell>
														{new Date(payment.created_at).toLocaleDateString(
															locale
														)}
													</TableCell>
													<TableCell>
														{payment.amount.toLocaleString(locale)}
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
							<h3 className="text-lg font-semibold">{t('common.summary')}</h3>
							<div className="grid gap-2">
								<div className="flex justify-between">
									<span>{t('invoices.summary_total_orders')}</span>
									<span className="font-semibold">
										{totals.orders.toLocaleString(locale)}
									</span>
								</div>
								<div className="flex justify-between">
									<span>{t('invoices.summary_total_payments')}</span>
									<span className="font-semibold">
										{totals.payments.toLocaleString(locale)}
									</span>
								</div>
								<div className="flex justify-between border-t pt-2">
									<span className="font-bold">{t('invoices.summary_balance')}</span>
									<span className="font-bold text-lg">
										{totals.balance.toLocaleString(locale)}
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
