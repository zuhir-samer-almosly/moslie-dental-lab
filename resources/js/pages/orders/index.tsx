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
								<TableHead>تاريخ الاستحقاق</TableHead>
								<TableHead>الحالة</TableHead>
								<TableHead>المبلغ</TableHead>
								<TableHead className="text-end">الإجراءات</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{orders.length === 0 ? (
								<TableRow>
									<TableCell colSpan={5} className="text-center">
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
											{new Date(order.due_date).toLocaleDateString('ar-SY')}
										</TableCell>
										<TableCell>
											<Badge>{ORDER_STATUSES[order.status]}</Badge>
										</TableCell>
										<TableCell>{order.amount.toLocaleString('ar-SY')}</TableCell>
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
