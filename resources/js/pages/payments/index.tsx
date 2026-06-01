import { Head, Link, router } from '@inertiajs/react'
import { Pencil, Plus, Trash2 } from 'lucide-react'
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
import type { BreadcrumbItem, DentistPayment } from '@/types'
import { useTranslation } from '@/lib/translations'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('payments.title'),
		href: '/payments',
	},
]

export default function PaymentsIndex({ payments }: { payments: DentistPayment[] }) {
	const { t, language } = useTranslation()
	const locale = 'en-US';
	const { url } = usePage()
	const searchParams = new URLSearchParams(url.split('?')[1] || '')
	const search = searchParams.get('search') || ''

	const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
		router.get(
			'/payments',
			{ search: e.target.value },
			{
				preserveState: true,
				replace: true,
			}
		)
	}

	const handleDelete = (id: number) => {
		router.delete(`/payments/${id}`)
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('payments.title')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<Heading variant="small" title={t('payments.title')} />

					<div className="flex items-center gap-2">
						<div className="relative">
							<Search className="absolute right-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
							<Input
								type="search"
								placeholder={`${t('action.show')}...`}
								className="w-full pl-4 pr-9 sm:w-64"
								defaultValue={search}
								onChange={handleSearch}
							/>
						</div>
						<Button asChild>
							<Link href="/payments/create">
								<Plus className="mr-2 h-4 w-4" />
								{t('payments.add')}
							</Link>
						</Button>
					</div>
				</div>

				<div className="rounded-md border">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>{t('order.dentist_name')}</TableHead>
								<TableHead>{t('payment.date')}</TableHead>
								<TableHead>{t('payment.amount')}</TableHead>
								<TableHead className="w-[100px]">{t('common.actions')}</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{payments.length === 0 ? (
								<TableRow>
									<TableCell colSpan={4} className="h-24 text-center">
										{t('common.no_data')}
									</TableCell>
								</TableRow>
							) : (
								payments.map((payment) => (
									<TableRow key={payment.id}>
										<TableCell className="font-medium">
											<Link href={`/payments/${payment.id}/edit`} className="hover:underline">
												{payment.dentist?.name}
											</Link>
										</TableCell>
										<TableCell>
											{new Date(payment.created_at).toLocaleDateString(locale)}
										</TableCell>
										<TableCell className="text-green-600 font-semibold">
											+{payment.amount.toLocaleString(locale)}
										</TableCell>
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
															<AlertDialogTitle>{t('payments.delete_confirm')}</AlertDialogTitle>
															<AlertDialogDescription>
																لا يمكن التراجع عن هذا الإجراء بعد تنفيذه.
															</AlertDialogDescription>
														</AlertDialogHeader>
														<AlertDialogFooter>
															<AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
															<AlertDialogAction
																onClick={() => handleDelete(payment.id)}
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
