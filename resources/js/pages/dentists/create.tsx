import { Head, useForm } from '@inertiajs/react'
import { ArrowRight } from 'lucide-react'
import Heading from '@/components/heading'
import InputError from '@/components/input-error'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/app-layout'
import type { BreadcrumbItem } from '@/types'
import { WORK_TYPES } from '@/types'

const breadcrumbs: BreadcrumbItem[] = [
	{
		title: 'أطباء الأسنان',
		href: '/dentists',
	},
	{
		title: 'إضافة طبيب',
		href: '/dentists/create',
	},
]

export default function DentistsCreate() {
	const { data, setData, post, processing, errors } = useForm({
		name: '',
		phone: '',
		address: '',
		price_list: {} as Record<string, number>,
	})

	const updatePrice = (type: string, value: string) => {
		const numValue = value === '' ? 0 : parseFloat(value)
		setData('price_list', { ...data.price_list, [type]: numValue })
	}

	const handleSubmit = (e: React.FormEvent) => {
		e.preventDefault()
		post('/dentists')
	}

	return (
		<AppLayout breadcrumbs={breadcrumbs}>
			<Head title="إضافة طبيب" />

			<div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
				<Button variant="ghost" size="sm" className="w-fit" onClick={() => window.history.back()}>
					<ArrowRight className="h-4 w-4" />
					رجوع
				</Button>

				<Heading variant="small" title="إضافة طبيب جديد" />

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
							حدد السعر الافتراضي لكل نوع عمل لهذا الطبيب. سيتم ملء السعر تلقائياً عند إنشاء الطلبات.
						</p>
						<div className="rounded-lg border">
							{WORK_TYPES.map((type, index) => (
								<div
									key={type}
									className={`flex items-center justify-between gap-4 px-4 py-3 ${index < WORK_TYPES.length - 1 ? 'border-b' : ''}`}
								>
									<span className="text-sm font-medium min-w-[120px]">{type}</span>
									<Input
										type="number"
										min="0"
										className="w-32"
										placeholder="0"
										value={data.price_list[type] || ''}
										onChange={(e) => updatePrice(type, e.target.value)}
									/>
								</div>
							))}
						</div>
						<InputError message={errors.price_list} />
					</div>

					<Button type="submit" disabled={processing}>
						حفظ
					</Button>
				</form>
			</div>
		</AppLayout>
	)
}
