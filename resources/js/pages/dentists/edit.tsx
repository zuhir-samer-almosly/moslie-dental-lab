import { Head, useForm } from '@inertiajs/react'
import { ArrowRight } from 'lucide-react'
import Heading from '@/components/heading'
import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Dentist } from '@/types'
import { useTranslation } from '@/lib/translations'

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
	{
		title: t('dentists.title'),
		href: '/dentists',
	},
	{
		title: t('dentists.edit'),
		href: '#',
	},
]

export default function DentistsEdit({ dentist }: { dentist: Dentist }) {
	const { t } = useTranslation()
	const { data, setData, put, processing, errors } = useForm({
		name: dentist.name,
		phone: dentist.phone || '',
		address: dentist.address || '',
	})

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		put(`/dentists/${dentist.id}`)
	}

	return (
		<AppLayout breadcrumbs={getBreadcrumbs(t)}>
			<Head title={t('dentists.edit')} />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<Button variant="ghost" size="sm" className="w-fit" onClick={() => window.history.back()}>
					<ArrowRight className="h-4 w-4" />
					{t('action.back')}
				</Button>

				<Heading variant="small" title={t('dentists.edit')} />

				<form onSubmit={handleSubmit} className="max-w-xl space-y-6">
					<div className="grid gap-2">
						<Label htmlFor="name">{t('dentist.name_req')}</Label>
						<Input
							id="name"
							value={data.name}
							onChange={(e) => setData('name', e.target.value)}
							required
						/>
						<InputError message={errors.name} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="phone">
							{t('dentist.phone')} <span className="text-muted-foreground">{t('common.optional')}</span>
						</Label>
						<Input
							id="phone"
							value={data.phone}
							onChange={(e) => setData('phone', e.target.value)}
						/>
						<InputError message={errors.phone} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="address">
							{t('dentist.address')} <span className="text-muted-foreground">{t('common.optional')}</span>
						</Label>
						<Input
							id="address"
							value={data.address}
							onChange={(e) => setData('address', e.target.value)}
						/>
						<InputError message={errors.address} />
					</div>

					<Button type="submit" disabled={processing}>
						{t('action.update')}
					</Button>
				</form>
			</div>
		</AppLayout>
	)
}
