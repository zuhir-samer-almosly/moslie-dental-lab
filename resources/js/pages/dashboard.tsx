import { Head, Link } from '@inertiajs/react'
import {
	ClipboardList,
	CreditCard,
	TrendingUp,
	Users,
	DollarSign,
	ArrowUpRight,
} from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import {
	Card,
	CardContent,
	CardDescription,
	CardHeader,
	CardTitle,
} from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, DentistPayment, Order } from '@/types'
import { ORDER_STATUSES } from '@/types'
import { dashboard } from '@/routes'

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'لوحة التحكم',
		href: dashboard().url,
	},
]

type DashboardStats = {
	dentists: number
	orders: number
	pending_orders: number
	total_orders_amount: number
	total_payments_amount: number
	balance: number
}

type DashboardProps = {
	stats: DashboardStats
	recentOrders: Order[]
	recentPayments: DentistPayment[]
}

export default function Dashboard({ stats, recentOrders, recentPayments }: DashboardProps) {
	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="لوحة التحكم" />
			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<div className="flex items-center justify-between">
					<h1 className="text-2xl font-bold">لوحة التحكم</h1>
				</div>

				{/* Stats Cards */}
				<div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
					<Card>
						<CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
							<CardTitle className="text-sm font-medium">إجمالي الأطباء</CardTitle>
							<Users className="h-4 w-4 text-muted-foreground" />
						</CardHeader>
						<CardContent>
							<div className="text-2xl font-bold">{stats.dentists}</div>
							<p className="text-xs text-muted-foreground">عدد أطباء الأسنان المسجلين</p>
						</CardContent>
					</Card>

					<Card>
						<CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
							<CardTitle className="text-sm font-medium">إجمالي الطلبات</CardTitle>
							<ClipboardList className="h-4 w-4 text-muted-foreground" />
						</CardHeader>
						<CardContent>
							<div className="text-2xl font-bold">{stats.orders}</div>
							<p className="text-xs text-muted-foreground">
								{stats.pending_orders} قيد الانتظار
							</p>
						</CardContent>
					</Card>

					<Card>
						<CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
							<CardTitle className="text-sm font-medium">قيمة الطلبات</CardTitle>
							<TrendingUp className="h-4 w-4 text-muted-foreground" />
						</CardHeader>
						<CardContent>
							<div className="text-2xl font-bold">
								{stats.total_orders_amount.toLocaleString('en-US')}
							</div>
							<p className="text-xs text-muted-foreground">إجمالي قيمة الطلبات</p>
						</CardContent>
					</Card>

					<Card>
						<CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
							<CardTitle className="text-sm font-medium">المدفوعات</CardTitle>
							<CreditCard className="h-4 w-4 text-muted-foreground" />
						</CardHeader>
						<CardContent>
							<div className="text-2xl font-bold">
								{stats.total_payments_amount.toLocaleString('en-US')}
							</div>
							<p className="text-xs text-muted-foreground">إجمالي المدفوعات</p>
						</CardContent>
					</Card>

					<Card className="md:col-span-2">
						<CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
							<CardTitle className="text-sm font-medium">الرصيد المتبقي</CardTitle>
							<DollarSign className="h-4 w-4 text-muted-foreground" />
						</CardHeader>
						<CardContent>
							<div className="text-2xl font-bold">{stats.balance.toLocaleString('en-US')}</div>
							<p className="text-xs text-muted-foreground">
								الفرق بين الطلبات والمدفوعات
							</p>
						</CardContent>
					</Card>
				</div>

				{/* Recent Activity */}
				<div className="grid gap-4 md:grid-cols-2">
					{/* Recent Orders */}
					<Card>
						<CardHeader>
							<div className="flex items-center justify-between">
								<div>
									<CardTitle>أحدث الطلبات</CardTitle>
									<CardDescription>آخر 5 طلبات</CardDescription>
								</div>
								<Button asChild variant="ghost" size="sm">
									<Link href="/orders">
										عرض الكل
										<ArrowUpRight className="h-4 w-4 ms-1" />
									</Link>
								</Button>
							</div>
						</CardHeader>
						<CardContent>
							<div className="space-y-4">
								{recentOrders.length === 0 ? (
									<p className="text-sm text-muted-foreground text-center py-4">
										لا توجد طلبات
									</p>
								) : (
									recentOrders.map((order) => (
										<div
											key={order.id}
											className="flex items-center justify-between border-b pb-3 last:border-0"
										>
											<div className="space-y-1">
												<p className="text-sm font-medium">
													{order.dentist?.name}
												</p>
												<div className="flex items-center gap-2">
													<Badge variant="outline" className="text-xs">
														{ORDER_STATUSES[order.status]}
													</Badge>
													<span className="text-xs text-muted-foreground">
														{new Date(order.created_at).toLocaleDateString(
															'en-US'
														)}
													</span>
												</div>
											</div>
											<div className="text-sm font-semibold">
												{order.amount.toLocaleString('en-US')}
											</div>
										</div>
									))
								)}
							</div>
						</CardContent>
					</Card>

					{/* Recent Payments */}
					<Card>
						<CardHeader>
							<div className="flex items-center justify-between">
								<div>
									<CardTitle>أحدث المدفوعات</CardTitle>
									<CardDescription>آخر 5 مدفوعات</CardDescription>
								</div>
								<Button asChild variant="ghost" size="sm">
									<Link href="/payments">
										عرض الكل
										<ArrowUpRight className="h-4 w-4 ms-1" />
									</Link>
								</Button>
							</div>
						</CardHeader>
						<CardContent>
							<div className="space-y-4">
								{recentPayments.length === 0 ? (
									<p className="text-sm text-muted-foreground text-center py-4">
										لا توجد مدفوعات
									</p>
								) : (
									recentPayments.map((payment) => (
										<div
											key={payment.id}
											className="flex items-center justify-between border-b pb-3 last:border-0"
										>
											<div className="space-y-1">
												<p className="text-sm font-medium">
													{payment.dentist?.name}
												</p>
												<span className="text-xs text-muted-foreground">
													{new Date(payment.payment_date || payment.created_at).toLocaleDateString(
														'en-US'
													)}
												</span>
											</div>
											<div className="text-sm font-semibold text-green-600">
												+{payment.amount.toLocaleString('en-US')}
											</div>
										</div>
									))
								)}
							</div>
						</CardContent>
					</Card>
				</div>

				{/* Quick Actions */}
				<Card>
					<CardHeader>
						<CardTitle>إجراءات سريعة</CardTitle>
						<CardDescription>الإجراءات الأكثر استخداماً</CardDescription>
					</CardHeader>
					<CardContent>
						<div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
							<Button asChild variant="outline" className="h-auto flex-col gap-2 p-4">
								<Link href="/dentists/create">
									<Users className="h-5 w-5" />
									<span>إضافة طبيب</span>
								</Link>
							</Button>
							<Button asChild variant="outline" className="h-auto flex-col gap-2 p-4">
								<Link href="/orders/create">
									<ClipboardList className="h-5 w-5" />
									<span>إضافة طلب</span>
								</Link>
							</Button>
							<Button asChild variant="outline" className="h-auto flex-col gap-2 p-4">
								<Link href="/payments/create">
									<CreditCard className="h-5 w-5" />
									<span>إضافة دفعة</span>
								</Link>
							</Button>
							<Button asChild variant="outline" className="h-auto flex-col gap-2 p-4">
								<Link href="/invoices">
									<TrendingUp className="h-5 w-5" />
									<span>عرض الفواتير</span>
								</Link>
							</Button>
						</div>
					</CardContent>
				</Card>
			</div>
		</AppLayout>
	)
}
