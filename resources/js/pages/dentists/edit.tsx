import { Head, useForm } from '@inertiajs/react'
import { ArrowRight } from 'lucide-react'
import Heading from '@/components/heading'
import InputError from '@/components/input-error'
import PriceListEditor, { type PriceRow } from '@/components/price-list-editor'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem, Dentist } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'أطباء الأسنان',
		href: '/dentists',
	},
	{
		title: 'تعديل طبيب',
		href: '#',
	},
]

export default function DentistsEdit({ dentist }: { dentist: Dentist }) {
	const { data, setData, transform, put, processing, errors } = useForm({
		name: dentist.name,
		phone: dentist.phone || '',
		address: dentist.address || '',
		price_list: Object.entries(dentist.price_list || {}).map(([name, price]) => ({
			name,
			price,
		})) as PriceRow[],
	})

	transform((payload) => ({
		...payload,
		price_list: Object.fromEntries(
			payload.price_list
				.filter((row) => row.name.trim() !== '')
				.map((row) => [row.name.trim(), row.price])
		),
	}))

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		put(`/dentists/${dentist.id}`)
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="تعديل طبيب" />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<Button variant="ghost" size="sm" className="w-fit" onClick={() => window.history.back()}>
					<ArrowRight className="h-4 w-4" />
					رجوع
				</Button>

				<Heading variant="small" title="تعديل طبيب" />

				<form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
					<div className="grid gap-2">
						<Label htmlFor="name">الاسم *</Label>
						<Input
							id="name"
							value={data.name}
							onChange={(e) => setData('name', e.target.value)}
							required
						/>
						<InputError message={errors.name} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="phone">الهاتف</Label>
						<Input
							id="phone"
							value={data.phone}
							onChange={(e) => setData('phone', e.target.value)}
						/>
						<InputError message={errors.phone} />
					</div>

					<div className="grid gap-2">
						<Label htmlFor="address">العنوان</Label>
						<Textarea
							id="address"
							value={data.address}
							onChange={(e) => setData('address', e.target.value)}
							rows={3}
						/>
						<InputError message={errors.address} />
					</div>

					<div className="space-y-3">
						<Label>قائمة الأسعار</Label>
						<p className="text-sm text-muted-foreground">
							حدد أنواع العمل وأسعارها لهذا الطبيب. ستظهر هذه الأنواع عند إضافة عناصر الطلب ويُملأ سعرها تلقائياً.
						</p>
						<PriceListEditor
							value={data.price_list}
							onChange={(rows) => setData('price_list', rows)}
						/>
						<InputError message={errors.price_list} />
					</div>

					<Button type="submit" disabled={processing}>
						تحديث
					</Button>
				</form>
			</div>
		</AppLayout>
	)
}
