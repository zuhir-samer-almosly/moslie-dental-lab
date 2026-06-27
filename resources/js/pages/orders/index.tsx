import { Head, Link, router } from '@inertiajs/react'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { Dash, formatDate, itemAmount, itemDate, itemPatient, itemTeeth, TeethBadges } from '@/components/order-display'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Order } from '@/types'
import { ORDER_STATUSES } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'الطلبات',
		href: '/orders',
	},
]

export default function OrdersIndex({ orders }: { orders: Order[] }) {
	const handleDelete = (id: number) => {
		if (confirm('هل أنت متأكد من حذف هذا الطلب؟')) {
			router.delete(`/orders/${id}`)
		}
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="الطلبات" />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<div className="flex items-center justify-between">
					<h1 className="text-2xl font-bold">الطلبات</h1>
					<Button asChild>
						<Link href="/orders/create">
							<Plus className="h-4 w-4" />
							إضافة طلب
						</Link>
					</Button>
				</div>

				<div className="rounded-lg border">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>اسم الطبيب</TableHead>
								<TableHead>اسم المريض</TableHead>
								<TableHead>العنصر</TableHead>
								<TableHead>الأسنان</TableHead>
								<TableHead>التاريخ</TableHead>
								<TableHead>الحالة</TableHead>
								<TableHead>المبلغ</TableHead>
								<TableHead className="text-end">الإجراءات</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{orders.length === 0 ? (
								<TableRow>
									<TableCell colSpan={8} className="text-center">
										لا توجد بيانات
									</TableCell>
								</TableRow>
							) : (
								orders.map((order) => {
									const items = order.items ?? []
									const span = Math.max(items.length, 1)

									// Order-level cells are rendered once and span all the
									// order's item rows (dentist / status / amount / actions).
									const dentistCell = (
										<TableCell rowSpan={span} className="border-s align-middle font-medium">
											{order.dentist?.name}
										</TableCell>
									)
									const statusCell = (
										<TableCell rowSpan={span} className="border-s align-middle">
											<Badge>{ORDER_STATUSES[order.status]}</Badge>
										</TableCell>
									)
									const actionsCell = (
										<TableCell rowSpan={span} className="align-middle text-end">
											<div className="flex justify-end gap-2">
												<Button asChild variant="outline" size="sm">
													<Link href={`/orders/${order.id}/edit`}>
														<Pencil className="h-4 w-4" />
													</Link>
												</Button>
												<Button
													variant="destructive"
													size="sm"
													onClick={() => handleDelete(order.id)}
												>
													<Trash2 className="h-4 w-4" />
												</Button>
											</div>
										</TableCell>
									)

									if (items.length === 0) {
										return (
											<TableRow key={order.id}>
												{dentistCell}
												<TableCell><Dash /></TableCell>
												<TableCell><Dash /></TableCell>
												<TableCell><Dash /></TableCell>
												<TableCell className="whitespace-nowrap">
													{formatDate(order.due_date) || <Dash />}
												</TableCell>
												{statusCell}
												<TableCell className="tabular-nums">
													{order.amount.toLocaleString('en-US')}
												</TableCell>
												{actionsCell}
											</TableRow>
										)
									}

									return items.map((item, index) => (
										<TableRow key={item.id}>
											{index === 0 && dentistCell}
											<TableCell>{itemPatient(item) || <Dash />}</TableCell>
											<TableCell className="whitespace-nowrap">
												{item.type}{' '}
												<span className="text-muted-foreground">× {item.quantity}</span>
											</TableCell>
											<TableCell>
												<TeethBadges teeth={itemTeeth(item)} />
											</TableCell>
											<TableCell className="whitespace-nowrap">
												{formatDate(itemDate(item)) || formatDate(order.due_date) || <Dash />}
											</TableCell>
											{index === 0 && statusCell}
											<TableCell className="tabular-nums">
												{itemAmount(item).toLocaleString('en-US')}
											</TableCell>
											{index === 0 && actionsCell}
										</TableRow>
									))
								})
							)}
						</TableBody>
					</Table>
				</div>
			</div>
		</AppLayout>
	)
}
