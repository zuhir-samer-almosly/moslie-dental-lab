import { Head, Link, router, usePage } from '@inertiajs/react'
import { Pencil, Plus, Search, Trash2 } from 'lucide-react'
import Heading from '@/components/heading'
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
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '@/components/ui/table'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Dentist } from '@/types'
import { useTranslation } from '@/lib/translations'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('dentists.title'),
		href: '/dentists',
	},
]

export default function DentistsIndex({ dentists }: { dentists: Dentist[] }) {
	const { t } = useTranslation()
	const { url } = usePage()
	const searchParams = new URLSearchParams(url.split('?')[1] || '')
	const search = searchParams.get('search') || ''

	const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
		router.get(
			'/dentists',
			{ search: e.target.value },
			{
				preserveState: true,
				replace: true,
			}
		)
	}

	const handleDelete = (id: number) => {
		router.delete(`/dentists/${id}`)
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('dentists.title')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
					<Heading variant="small" title={t('dentists.title')} />

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
							<Link href="/dentists/create">
								<Plus className="mr-2 h-4 w-4" />
								{t('dentists.add')}
							</Link>
						</Button>
					</div>
				</div>

				<div className="rounded-md border">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>{t('dentist.name')}</TableHead>
								<TableHead>{t('dentist.phone')}</TableHead>
								<TableHead>{t('dentist.address')}</TableHead>
								<TableHead>{t('dashboard.total_orders')}</TableHead>
								<TableHead className="w-[100px]">{t('common.actions')}</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{dentists.length === 0 ? (
								<TableRow>
									<TableCell colSpan={5} className="h-24 text-center">
										{t('common.no_data')}
									</TableCell>
								</TableRow>
							) : (
								dentists.map((dentist) => (
									<TableRow key={dentist.id}>
										<TableCell className="font-medium">
											<Link href={`/dentists/${dentist.id}/edit`} className="hover:underline">
												{dentist.name}
											</Link>
										</TableCell>
										<TableCell>{dentist.phone || '-'}</TableCell>
										<TableCell>{dentist.address || '-'}</TableCell>
										<TableCell>{(dentist as any).orders_count || 0}</TableCell>
										<TableCell>
											<div className="flex items-center gap-2">
												<Button asChild variant="ghost" size="icon">
													<Link href={`/dentists/${dentist.id}/edit`}>
														<Pencil className="h-4 w-4" />
													</Link>
												</Button>
												<AlertDialog>
													<AlertDialogTrigger asChild>
														<Button variant="ghost" size="icon" className="text-destructive">
															<Trash2 className="h-4 w-4" />
														</Button>
													</AlertDialogTrigger>
													<AlertDialogContent>
														<AlertDialogHeader>
															<AlertDialogTitle>{t('dentists.delete_confirm')}</AlertDialogTitle>
															<AlertDialogDescription>
																{t('dentists.delete_warning')}
															</AlertDialogDescription>
														</AlertDialogHeader>
														<AlertDialogFooter>
															<AlertDialogCancel>{t('action.cancel')}</AlertDialogCancel>
															<AlertDialogAction
																onClick={() => handleDelete(dentist.id)}
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
