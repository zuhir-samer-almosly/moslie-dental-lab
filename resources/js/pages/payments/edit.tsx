import { Head, useForm } from '@inertiajs/react'
import { ArrowRight } from 'lucide-react'
import Heading from '@/components/heading'
import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Dentist, DentistPayment } from '@/types'
import { useTranslation } from '@/lib/translations'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('payments.title'),
		href: '/payments',
	},
	{
		title: t('payments.edit'),
		href: '#',
	},
]

export default function PaymentsEdit({
	payment,
	dentists,
}: {
	payment: DentistPayment
	dentists: Dentist[]
}) {
	const { t } = useTranslation()
	const { data, setData, put, processing, errors } = useForm({
		dentist_id: payment.dentist_id.toString(),
		amount: payment.amount.toString(),
	})

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		put(`/payments/${payment.id}`)
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('payments.edit')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<Button variant="ghost" size="sm" className="w-fit" onClick={() => window.history.back()}>
					<ArrowRight className="h-4 w-4" />
					{t('action.back')}
				</Button>

				<Heading variant="small" title={t('payments.edit')} />

				<form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
					<div className="grid gap-2">
						<Label htmlFor="dentist_id">{t('order.dentist')}</Label>
						<Select
							value={data.dentist_id}
							onValueChange={(value) => setData('dentist_id', value)}
						>
							<SelectTrigger>
								<SelectValue placeholder={t('order.select_dentist')} />
							</SelectTrigger>
							<SelectContent>
								{dentists.map((dentist) => (
									<SelectItem key={dentist.id} value={dentist.id.toString()}>
										{dentist.name}
									</SelectItem>
								))}
							</SelectContent>
						</Select>
						<InputError message={errors.dentist_id} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="amount">{t('payment.amount')}</Label>
						<Input
							id="amount"
							type="number"
							min="1"
							value={data.amount}
							onChange={(e) => setData('amount', e.target.value)}
							required
						/>
						<InputError message={errors.amount} />
					</div>

					<Button type="submit" disabled={processing}>
						{t('action.update')}
					</Button>
				</form>
			</div>
		</AppLayout>
	)
}
