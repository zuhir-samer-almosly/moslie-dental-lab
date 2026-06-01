// Components
import { Form, Head } from '@inertiajs/react';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { logout } from '@/routes';
import { send } from '@/routes/verification';
import { useTranslation } from '@/lib/translations';

export default function VerifyEmail({ status }: { status?: string }) {
    const { t } = useTranslation();
    return (
        <AuthLayout
            title={t('auth.verify.title')}
            description={t('auth.verify.desc')}
        >
            <Head title={t('auth.verify.title')} />

            {status === 'verification-link-sent' && (
                <div className="mb-4 text-center text-sm font-medium text-green-600">
                    {t('settings.profile.sent')}
                </div>
            )}

            <Form {...send.form()} className="mt-6 flex items-center justify-between">
                {({ processing }) => (
                    <>
                        <Button disabled={processing}>
                            {processing && <Spinner />}
                            {t('settings.profile.resend')}
                        </Button>

                        <TextLink
                            href={logout()}
                            method="post"
                            as="button"
                        >
                            {t('user.logout')}
                        </TextLink>
                    </>
                )}
            </Form>
        </AuthLayout>
    );
}
