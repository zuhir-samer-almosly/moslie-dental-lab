import { Head, Link, router, usePage } from '@inertiajs/react'
import { Pencil, Plus, Search, Trash2 } from 'lucide-react'
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
import { Input } from '@/components/ui/input'
import {
	AlertDialog,
	AlertDialogAction,
	AlertDialogCancel,
	AlertDialogContent,
	AlertDialogDescription,
	AlertDialogFooter,
	AlertDialogHeader,
	AlertDialogTitle,
	AlertDialogTrigger,
} from '@/components/ui/alert-dialog'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Order } from '@/types'
import { ORDER_STATUSES } from '@/types'
import { useTranslation } from '@/lib/translations'
import { Heading } from '@/components/heading'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('orders.title'),
		href: '/orders',
	},
]

export default function OrdersIndex({ orders }: { orders: Order[] }) {
	const { t, language } = useTranslation()
	const locale = 'en-US';
	const { url } = usePage()
	const searchParams = new URLSearchParams(url.split('?')[1] || '')
	const search = searchParams.get('search') || ''

	const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
		router.get(
			'/orders',
			{ search: e.target.value },
			{
				preserveState: true,
				replace: true,
			}
		)
	}

	const handleDelete = (id: number) => {
		router.delete(`/orders/${id}`)
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('orders.title')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<Heading variant="small" title={t('orders.title')} />

					<div className="flex items-center gap-2">
						<div className="relative">
							<Search className="absolute right-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
							<Input
								type="search"
								placeholder={`${t('action.search')}...`}
								className="w-full pl-4 pr-9 sm:w-64"
								defaultValue={search}
								onChange={handleSearch}
							/>
						</div>
						<Button asChild>
							<Link href="/orders/create">
								<Plus className="mr-2 h-4 w-4" />
								{t('orders.add')}
							</Link>
						</Button>
					</div>
				</div>

				<div className="rounded-md border">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>{t('order.dentist_name')}</TableHead>
								<TableHead>{t('order.due_date')}</TableHead>
								<TableHead>{t('order.status')}</TableHead>
								<TableHead>{t('order.amount')}</TableHead>
								<TableHead className="w-[100px]">{t('common.actions')}</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{orders.length === 0 ? (
								<TableRow>
									<TableCell colSpan={5} className="h-24 text-center">
										{t('common.no_data')}
									</TableCell>
								</TableRow>
							) : (
								orders.map((order) => (
									<TableRow key={order.id}>
										<TableCell className="font-medium">
											<Link href={`/orders/${order.id}/edit`} className="hover:underline">
												{order.dentist?.name}
											</Link>
										</TableCell>
										<TableCell>
											{order.due_date
												? new Date(order.due_date).toLocaleDateString(locale)
												: '-'}
										</TableCell>
										<TableCell>
											<Badge variant="secondary">{ORDER_STATUSES[order.status]}</Badge>
										</TableCell>
										<TableCell>{order.amount.toLocaleString(locale)}</TableCell>
										<TableCell>
											<div className="flex items-center gap-2">
												<AlertDialog>
													<AlertDialogTrigger asChild>
														<Button variant="ghost" size="icon" className="text-destructive">
															<Trash2 className="h-4 w-4" />
														</Button>
													</AlertDialogTrigger>
													<AlertDialogContent>
														<AlertDialogHeader>
															<AlertDialogTitle>{t('orders.delete_confirm')}</AlertDialogTitle>
															<AlertDialogDescription>
																{t('orders.delete_description')}
															</AlertDialogDescription>
														</AlertDialogHeader>
														<AlertDialogFooter>
															<AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
															<AlertDialogAction
																onClick={() => handleDelete(order.id)}
																className="bg-destructive hover:bg-destructive/90"
															>
																{t('action.delete')}
															</AlertDialogAction>
														</AlertDialogFooter>
													</AlertDialogContent>
												</AlertDialog>
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
