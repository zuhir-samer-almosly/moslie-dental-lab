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
import type { BreadcrumbItem, Dentist } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'أطباء الأسنان',
		href: '/dentists',
	},
]

export default function DentistsIndex({ dentists }: { dentists: Dentist[] }) {
	const handleDelete = (id: number) => {
		if (confirm('هل أنت متأكد من حذف هذا الطبيب؟')) {
			router.delete(`/dentists/${id}`)
		}
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="أطباء الأسنان" />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<div className="flex items-center justify-between">
					<h1 className="text-2xl font-bold">أطباء الأسنان</h1>
					<Button asChild>
						<Link href="/dentists/create">
							<Plus className="h-4 w-4" />
							إضافة طبيب
						</Link>
					</Button>
				</div>

				<div className="rounded-lg border">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>الاسم</TableHead>
								<TableHead>الهاتف</TableHead>
								<TableHead>العنوان</TableHead>
								<TableHead className="text-end">الإجراءات</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{dentists.length === 0 ? (
								<TableRow>
									<TableCell colSpan={4} className="text-center">
										لا توجد بيانات
									</TableCell>
								</TableRow>
							) : (
								dentists.map((dentist) => (
									<TableRow key={dentist.id}>
										<TableCell className="font-medium">{dentist.name}</TableCell>
										<TableCell>{dentist.phone || '-'}</TableCell>
										<TableCell>{dentist.address || '-'}</TableCell>
										<TableCell className="text-end">
											<div className="flex justify-end gap-2">
												<Button asChild variant="outline" size="sm">
													<Link href={`/dentists/${dentist.id}/edit`}>
														<Pencil className="h-4 w-4" />
													</Link>
												</Button>
												<Button
													variant="destructive"
													size="sm"
													onClick={() => handleDelete(dentist.id)}
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
