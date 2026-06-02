import { Head, Link, router } from '@inertiajs/react'
import { Pencil, Plus, Trash2 } from 'lucide-react'
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
								<TableHead>تاريخ الاستحقاق</TableHead>
								<TableHead>الحالة</TableHead>
								<TableHead>الأسنان</TableHead>
								<TableHead>المبلغ</TableHead>
								<TableHead className="text-end">الإجراءات</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{orders.length === 0 ? (
								<TableRow>
									<TableCell colSpan={7} className="text-center">
										لا توجد بيانات
									</TableCell>
								</TableRow>
							) : (
								orders.map((order) => (
									<TableRow key={order.id}>
										<TableCell className="font-medium">
											{order.dentist?.name}
										</TableCell>
										<TableCell>
											{(() => {
												const names = (order.items || [])
													.map((item) => (item.meta as Record<string, unknown> | null)?.patient_name as string | undefined)
													.filter((name): name is string => !!name && name.trim() !== '')
													.filter((v, i, a) => a.indexOf(v) === i)
												if (names.length === 0) return <span className="text-muted-foreground text-xs">—</span>
												return names.join('، ')
											})()}
										</TableCell>
										<TableCell>
											{new Date(order.due_date).toLocaleDateString('en-US')}
										</TableCell>
										<TableCell>
											<Badge>{ORDER_STATUSES[order.status]}</Badge>
										</TableCell>
										<TableCell>
											{(() => {
												const allTeeth = (order.items || [])
													.flatMap((item) => ((item.meta as Record<string, unknown> | null)?.selected_teeth as number[]) || [])
													.filter((v, i, a) => a.indexOf(v) === i)
													.sort((a, b) => a - b)
												if (allTeeth.length === 0) return <span className="text-muted-foreground text-xs">—</span>
												return (
													<div className="flex flex-wrap gap-1">
														{allTeeth.map((tooth: number) => (
															<span
																key={tooth}
																className="inline-flex items-center justify-center min-w-[22px] h-5 px-1 text-[10px] font-semibold rounded bg-primary/10 text-primary"
															>
																{tooth}
															</span>
														))}
													</div>
												)
											})()}
										</TableCell>
										<TableCell>{order.amount.toLocaleString('en-US')}</TableCell>
										<TableCell className="text-end">
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
									</TableRow>
								))
							)}
						</TableBody>
					</Table>
				</div>
			</div>
		</AppLayout>
	)
}
