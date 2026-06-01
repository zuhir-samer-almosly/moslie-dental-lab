import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';
import { edit as editAppearance } from '@/routes/appearance';
import { useTranslation } from '@/lib/translations';

const getBreadcrumbs = (t: any): BreadcrumbItem[] => [
    {
        title: t('settings.appearance'),
        href: editAppearance().url,
    },
];

export default function Appearance() {
    const { t } = useTranslation();

    return (
        <AppLayout breadcrumbs={getBreadcrumbs(t)}>
            <Head title={t('settings.appearance')} />

            <h1 className="sr-only">{t('settings.appearance')}</h1>

            <SettingsLayout>
                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title={t('settings.appearance')}
                        description={t('settings.appearance.desc')}
                    />
                    <AppearanceTabs />
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
