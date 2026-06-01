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
import type { BreadcrumbItem, Dentist } from '@/types'
import { useTranslation } from '@/lib/translations'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('payments.title'),
		href: '/payments',
	},
	{
		title: t('payments.add_new'),
		href: '/payments/create',
	},
]

export default function PaymentsCreate({ dentists }: { dentists: Dentist[] }) {
	const { t } = useTranslation()
	const { data, setData, post, processing, errors } = useForm({
		dentist_id: '',
		amount: '',
	})

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		post('/payments')
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('payments.add')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<Button variant="ghost" size="sm" className="w-fit" onClick={() => window.history.back()}>
					<ArrowRight className="h-4 w-4" />
					{t('action.back')}
				</Button>

				<Heading variant="small" title={t('payments.add_new')} />

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
						{t('action.save')}
					</Button>
				</form>
			</div>
		</AppLayout>
	)
}
