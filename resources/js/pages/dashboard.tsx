import { Head, Link } from '@inertiajs/react'
import {
	ArrowUpRight,
	ClipboardList,
	CreditCard,
	DollarSign,
	FileText,
	Plus,
	TrendingUp,
	Users,
	Wallet,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import AppLayout from '@/layouts/app-layout'
import { cn } from '@/lib/utils'
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

const nf = (value: number) => value.toLocaleString('en-US')
const formatDate = (value: string) => new Date(value).toLocaleDateString('en-US')

type StatTone = 'blue' | 'amber' | 'violet' | 'emerald' | 'rose'

const toneStyles: Record<StatTone, { icon: string; ring: string }> = {
	blue: { icon: 'bg-blue-500/10 text-blue-600 dark:text-blue-400', ring: 'hover:border-blue-500/40' },
	amber: { icon: 'bg-amber-500/10 text-amber-600 dark:text-amber-400', ring: 'hover:border-amber-500/40' },
	violet: { icon: 'bg-violet-500/10 text-violet-600 dark:text-violet-400', ring: 'hover:border-violet-500/40' },
	emerald: { icon: 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', ring: 'hover:border-emerald-500/40' },
	rose: { icon: 'bg-rose-500/10 text-rose-600 dark:text-rose-400', ring: 'hover:border-rose-500/40' },
}

function StatCard({
	title,
	value,
	hint,
	icon: Icon,
	tone,
	className,
}: {
	title: string
	value: string | number
	hint: string
	icon: LucideIcon
	tone: StatTone
	className?: string
}) {
	const styles = toneStyles[tone]
	return (
		<Card
			className={cn(
				'group gap-0 py-0 transition-colors duration-200',
				styles.ring,
				className
			)}
		>
			<CardContent className="flex items-start justify-between gap-4 p-5">
				<div className="space-y-2">
					<p className="text-sm font-medium text-muted-foreground">{title}</p>
					<p className="text-3xl font-bold tracking-tight tabular-nums">{value}</p>
					<p className="text-xs text-muted-foreground">{hint}</p>
				</div>
				<div
					className={cn(
						'flex size-11 shrink-0 items-center justify-center rounded-xl transition-transform duration-200 group-hover:scale-105',
						styles.icon
					)}
				>
					<Icon className="size-5" />
				</div>
			</CardContent>
		</Card>
	)
}

const quickActions: {
	href: string
	label: string
	icon: LucideIcon
	tone: StatTone
}[] = [
	{ href: '/dentists/create', label: 'إضافة طبيب', icon: Users, tone: 'blue' },
	{ href: '/orders/create', label: 'إضافة طلب', icon: ClipboardList, tone: 'amber' },
	{ href: '/payments/create', label: 'إضافة دفعة', icon: CreditCard, tone: 'emerald' },
	{ href: '/invoices', label: 'عرض الفواتير', icon: FileText, tone: 'violet' },
]

export default function Dashboard({ stats, recentOrders, recentPayments }: DashboardProps) {
	const balanceNegative = stats.balance < 0

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="لوحة التحكم" />
			<div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
				{/* Header */}
				<div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<div className="space-y-1">
						<h1 className="text-2xl font-bold tracking-tight">لوحة التحكم</h1>
						<p className="text-sm text-muted-foreground">
							نظرة عامة على أداء المختبر والنشاط الأخير
						</p>
					</div>
					<Button asChild size="lg" className="gap-2 sm:w-auto">
						<Link href="/orders/create">
							<Plus className="size-4" />
							إضافة طلب جديد
						</Link>
					</Button>
				</div>

				{/* Stats */}
				<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
					<StatCard
						title="إجمالي الأطباء"
						value={nf(stats.dentists)}
						hint="عدد أطباء الأسنان المسجلين"
						icon={Users}
						tone="blue"
					/>
					<StatCard
						title="إجمالي الطلبات"
						value={nf(stats.orders)}
						hint={`${nf(stats.pending_orders)} قيد الانتظار`}
						icon={ClipboardList}
						tone="amber"
					/>
					<StatCard
						title="قيمة الطلبات"
						value={nf(stats.total_orders_amount)}
						hint="إجمالي قيمة الطلبات"
						icon={TrendingUp}
						tone="violet"
					/>
					<StatCard
						title="المدفوعات"
						value={nf(stats.total_payments_amount)}
						hint="إجمالي المدفوعات المستلمة"
						icon={Wallet}
						tone="emerald"
					/>

					{/* Balance — highlighted */}
					<Card className="gap-0 py-0 sm:col-span-2 lg:col-span-1">
						<CardContent className="flex h-full items-center justify-between gap-4 bg-gradient-to-bl from-primary/5 to-transparent p-5">
							<div className="space-y-2">
								<p className="text-sm font-medium text-muted-foreground">الرصيد المتبقي</p>
								<p
									className={cn(
										'text-3xl font-bold tracking-tight tabular-nums',
										balanceNegative ? 'text-rose-600 dark:text-rose-400' : 'text-foreground'
									)}
								>
									{nf(stats.balance)}
								</p>
								<p className="text-xs text-muted-foreground">الفرق بين الطلبات والمدفوعات</p>
							</div>
							<div
								className={cn(
									'flex size-11 shrink-0 items-center justify-center rounded-xl',
									balanceNegative ? toneStyles.rose.icon : 'bg-primary/10 text-primary'
								)}
							>
								<DollarSign className="size-5" />
							</div>
						</CardContent>
					</Card>
				</div>

				{/* Recent activity */}
				<div className="grid gap-4 lg:grid-cols-2">
					{/* Recent orders */}
					<Card className="gap-0 py-0">
						<div className="flex items-center justify-between border-b p-5">
							<div className="space-y-0.5">
								<h2 className="font-semibold">أحدث الطلبات</h2>
								<p className="text-xs text-muted-foreground">آخر 5 طلبات</p>
							</div>
							<Button asChild variant="ghost" size="sm" className="gap-1 text-muted-foreground">
								<Link href="/orders">
									عرض الكل
									<ArrowUpRight className="size-4" />
								</Link>
							</Button>
						</div>
						<CardContent className="p-2">
							{recentOrders.length === 0 ? (
								<EmptyState icon={ClipboardList} text="لا توجد طلبات بعد" />
							) : (
								<ul>
									{recentOrders.map((order) => (
										<li key={order.id}>
											<Link
												href={`/orders/${order.id}`}
												className="flex items-center justify-between gap-3 rounded-lg px-3 py-3 transition-colors hover:bg-muted/60"
											>
												<div className="flex min-w-0 items-center gap-3">
													<span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400">
														<ClipboardList className="size-4" />
													</span>
													<div className="min-w-0 space-y-1">
														<p className="truncate text-sm font-medium">
															{order.dentist?.name ?? '—'}
														</p>
														<div className="flex items-center gap-2">
															<Badge variant="outline" className="text-[10px]">
																{ORDER_STATUSES[order.status]}
															</Badge>
															<span className="text-xs text-muted-foreground">
																{formatDate(order.created_at)}
															</span>
														</div>
													</div>
												</div>
												<span className="shrink-0 text-sm font-semibold tabular-nums">
													{nf(order.amount)}
												</span>
											</Link>
										</li>
									))}
								</ul>
							)}
						</CardContent>
					</Card>

					{/* Recent payments */}
					<Card className="gap-0 py-0">
						<div className="flex items-center justify-between border-b p-5">
							<div className="space-y-0.5">
								<h2 className="font-semibold">أحدث المدفوعات</h2>
								<p className="text-xs text-muted-foreground">آخر 5 مدفوعات</p>
							</div>
							<Button asChild variant="ghost" size="sm" className="gap-1 text-muted-foreground">
								<Link href="/payments">
									عرض الكل
									<ArrowUpRight className="size-4" />
								</Link>
							</Button>
						</div>
						<CardContent className="p-2">
							{recentPayments.length === 0 ? (
								<EmptyState icon={CreditCard} text="لا توجد مدفوعات بعد" />
							) : (
								<ul>
									{recentPayments.map((payment) => (
										<li
											key={payment.id}
											className="flex items-center justify-between gap-3 rounded-lg px-3 py-3"
										>
											<div className="flex min-w-0 items-center gap-3">
												<span className="flex size-9 shrink-0 items-center justify-center rounded-full bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
													<CreditCard className="size-4" />
												</span>
												<div className="min-w-0 space-y-1">
													<p className="truncate text-sm font-medium">
														{payment.dentist?.name ?? '—'}
													</p>
													<span className="text-xs text-muted-foreground">
														{formatDate(payment.payment_date || payment.created_at)}
													</span>
												</div>
											</div>
											<span className="shrink-0 text-sm font-semibold tabular-nums text-emerald-600 dark:text-emerald-400">
												+{nf(payment.amount)}
											</span>
										</li>
									))}
								</ul>
							)}
						</CardContent>
					</Card>
				</div>

				{/* Quick actions */}
				<div className="space-y-3">
					<div className="space-y-0.5">
						<h2 className="font-semibold">إجراءات سريعة</h2>
						<p className="text-xs text-muted-foreground">الإجراءات الأكثر استخداماً</p>
					</div>
					<div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
						{quickActions.map((action) => {
							const styles = toneStyles[action.tone]
							return (
								<Link
									key={action.href}
									href={action.href}
									className={cn(
										'group flex items-center gap-3 rounded-xl border bg-card p-4 shadow-sm transition-colors',
										styles.ring
									)}
								>
									<span
										className={cn(
											'flex size-10 shrink-0 items-center justify-center rounded-lg transition-transform duration-200 group-hover:scale-105',
											styles.icon
										)}
									>
										<action.icon className="size-5" />
									</span>
									<span className="text-sm font-medium">{action.label}</span>
								</Link>
							)
						})}
					</div>
				</div>
			</div>
		</AppLayout>
	)
}

function EmptyState({ icon: Icon, text }: { icon: LucideIcon; text: string }) {
	return (
		<div className="flex flex-col items-center justify-center gap-2 py-10 text-center">
			<span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
				<Icon className="size-5" />
			</span>
			<p className="text-sm text-muted-foreground">{text}</p>
		</div>
	)
}
