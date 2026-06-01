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

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'المدفوعات',
		href: '/payments',
	},
]

export default function PaymentsIndex({ payments }: { payments: DentistPayment[] }) {
	const handleDelete = (id: number) => {
		if (confirm('هل أنت متأكد من حذف هذه الدفعة؟')) {
			router.delete(`/payments/${id}`)
		}
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="المدفوعات" />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<div className="flex items-center justify-between">
					<h1 className="text-2xl font-bold">المدفوعات</h1>
					<Button asChild>
						<Link href="/payments/create">
							<Plus className="h-4 w-4" />
							إضافة دفعة
						</Link>
					</Button>
				</div>

				<div className="rounded-lg border">
					<Table>
						<TableHeader>
							<TableRow>
								<TableHead>اسم الطبيب</TableHead>
								<TableHead>المبلغ</TableHead>
								<TableHead>التاريخ</TableHead>
								<TableHead className="text-end">الإجراءات</TableHead>
							</TableRow>
						</TableHeader>
						<TableBody>
							{payments.length === 0 ? (
								<TableRow>
									<TableCell colSpan={4} className="text-center">
										لا توجد بيانات
									</TableCell>
								</TableRow>
							) : (
								payments.map((payment) => (
									<TableRow key={payment.id}>
										<TableCell className="font-medium">
											{payment.dentist?.name}
										</TableCell>
										<TableCell>{payment.amount.toLocaleString('en-US')}</TableCell>
										<TableCell>
											{new Date(payment.created_at).toLocaleDateString('en-US')}
										</TableCell>
										<TableCell className="text-end">
											<div className="flex justify-end gap-2">
												<Button asChild variant="outline" size="sm">
													<Link href={`/payments/${payment.id}/edit`}>
														<Pencil className="h-4 w-4" />
													</Link>
												</Button>
												<Button
													variant="destructive"
													size="sm"
													onClick={() => handleDelete(payment.id)}
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
