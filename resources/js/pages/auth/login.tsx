import { Form, Head } from '@inertiajs/react';
import { Eye, EyeOff, Languages, Lock, Mail } from 'lucide-react';
import { useState } from 'react';
import AppLogoIcon from '@/components/app-logo-icon';
import AppearanceMenu from '@/components/appearance-menu';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

type Lang = 'ar' | 'en';

const copy = {
    ar: {
        dir: 'rtl' as const,
        brandName: 'مختبر زهير للأسنان',
        brandTagline: 'نظام إدارة مختبر الأسنان',
        brandDesc: 'إدارة الأطباء والطلبات والمدفوعات في مكانٍ واحد.',
        welcome: 'مرحباً بعودتك',
        subtitle: 'سجّل الدخول للمتابعة إلى لوحة التحكم',
        email: 'البريد الإلكتروني',
        emailPlaceholder: 'email@example.com',
        password: 'كلمة المرور',
        passwordPlaceholder: 'أدخل كلمة المرور',
        remember: 'تذكّرني',
        forgot: 'نسيت كلمة المرور؟',
        submit: 'تسجيل الدخول',
        switchLang: 'English',
        showPassword: 'إظهار كلمة المرور',
        hidePassword: 'إخفاء كلمة المرور',
    },
    en: {
        dir: 'ltr' as const,
        brandName: 'Zoher Dental Lab',
        brandTagline: 'Dental Lab Management System',
        brandDesc: 'Manage dentists, orders, and payments in one place.',
        welcome: 'Welcome back',
        subtitle: 'Sign in to continue to your dashboard',
        email: 'Email address',
        emailPlaceholder: 'email@example.com',
        password: 'Password',
        passwordPlaceholder: 'Enter your password',
        remember: 'Remember me',
        forgot: 'Forgot password?',
        submit: 'Log in',
        switchLang: 'العربية',
        showPassword: 'Show password',
        hidePassword: 'Hide password',
    },
};

export default function Login({ status, canResetPassword }: Props) {
    const [lang, setLang] = useState<Lang>(() => {
        if (typeof window === 'undefined') return 'ar';
        const stored = window.localStorage.getItem('login_lang');
        return stored === 'ar' || stored === 'en' ? stored : 'ar';
    });
    const [showPassword, setShowPassword] = useState(false);

    const t = copy[lang];

    const toggleLang = () => {
        const next: Lang = lang === 'ar' ? 'en' : 'ar';
        setLang(next);
        localStorage.setItem('login_lang', next);
    };

    return (
        <>
            <Head title={t.submit} />

            <div
                dir={t.dir}
                className="grid min-h-svh bg-background lg:grid-cols-2"
            >
                {/* Brand / visual panel */}
                <div className="relative hidden overflow-hidden bg-gradient-to-br from-teal-600 via-cyan-700 to-slate-900 lg:flex lg:flex-col lg:justify-between lg:p-12">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0 [background-image:radial-gradient(circle_at_1px_1px,white_1px,transparent_0)] [background-size:22px_22px] opacity-20"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-white/10 blur-3xl"
                    />

                    <div className="relative flex items-center gap-3 text-white">
                        <div className="flex size-11 items-center justify-center rounded-xl bg-white/15 backdrop-blur">
                            <AppLogoIcon className="size-6 fill-current text-white" />
                        </div>
                        <span className="text-lg font-semibold">
                            {t.brandName}
                        </span>
                    </div>

                    <div className="relative max-w-md text-white">
                        <h2 className="text-3xl leading-snug font-bold">
                            {t.brandTagline}
                        </h2>
                        <p className="mt-4 text-base text-white/80">
                            {t.brandDesc}
                        </p>
                    </div>

                    <p className="relative text-sm text-white/60">
                        © {new Date().getFullYear()} {t.brandName}
                    </p>
                </div>

                {/* Form panel */}
                <div className="relative flex flex-col items-center justify-center p-6 sm:p-10">
                    <div className="absolute top-4 inline-flex items-center gap-1 ltr:right-4 rtl:left-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            className="gap-2"
                            onClick={toggleLang}
                        >
                            <Languages className="size-4" />
                            {t.switchLang}
                        </Button>
                        <AppearanceMenu />
                    </div>

                    <div className="w-full max-w-sm">
                        {/* Mobile brand */}
                        <div className="mb-8 flex flex-col items-center gap-3 lg:hidden">
                            <div className="flex size-12 items-center justify-center rounded-xl bg-gradient-to-br from-teal-600 to-cyan-700">
                                <AppLogoIcon className="size-6 fill-current text-white" />
                            </div>
                            <span className="text-lg font-semibold">
                                {t.brandName}
                            </span>
                        </div>

                        <div className="mb-8 text-start">
                            <h1 className="text-2xl font-bold tracking-tight">
                                {t.welcome}
                            </h1>
                            <p className="mt-2 text-sm text-muted-foreground">
                                {t.subtitle}
                            </p>
                        </div>

                        {status && (
                            <div className="mb-6 rounded-md bg-green-50 px-4 py-3 text-center text-sm font-medium text-green-700 dark:bg-green-950 dark:text-green-400">
                                {status}
                            </div>
                        )}

                        <Form
                            {...store.form()}
                            resetOnSuccess={['password']}
                            className="flex flex-col gap-5"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="email">{t.email}</Label>
                                        <div className="relative">
                                            <Mail className="pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground ltr:left-3 rtl:right-3" />
                                            <Input
                                                id="email"
                                                type="email"
                                                name="email"
                                                required
                                                autoFocus
                                                tabIndex={1}
                                                autoComplete="email"
                                                placeholder={t.emailPlaceholder}
                                                className="ltr:pl-9 rtl:pr-9"
                                            />
                                        </div>
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <div className="flex items-center">
                                            <Label htmlFor="password">
                                                {t.password}
                                            </Label>
                                            {canResetPassword && (
                                                <TextLink
                                                    href={request()}
                                                    className="text-sm ltr:ml-auto rtl:mr-auto"
                                                    tabIndex={5}
                                                >
                                                    {t.forgot}
                                                </TextLink>
                                            )}
                                        </div>
                                        <div className="relative">
                                            <Lock className="pointer-events-none absolute top-1/2 size-4 -translate-y-1/2 text-muted-foreground ltr:left-3 rtl:right-3" />
                                            <Input
                                                id="password"
                                                type={
                                                    showPassword
                                                        ? 'text'
                                                        : 'password'
                                                }
                                                name="password"
                                                required
                                                tabIndex={2}
                                                autoComplete="current-password"
                                                placeholder={
                                                    t.passwordPlaceholder
                                                }
                                                className="px-9"
                                            />
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setShowPassword((v) => !v)
                                                }
                                                aria-label={
                                                    showPassword
                                                        ? t.hidePassword
                                                        : t.showPassword
                                                }
                                                tabIndex={-1}
                                                className="absolute top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:text-foreground ltr:right-2 rtl:left-2"
                                            >
                                                {showPassword ? (
                                                    <EyeOff className="size-4" />
                                                ) : (
                                                    <Eye className="size-4" />
                                                )}
                                            </button>
                                        </div>
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <Checkbox
                                            id="remember"
                                            name="remember"
                                            tabIndex={3}
                                        />
                                        <Label
                                            htmlFor="remember"
                                            className="font-normal"
                                        >
                                            {t.remember}
                                        </Label>
                                    </div>

                                    <Button
                                        type="submit"
                                        className="mt-2 h-10 w-full"
                                        tabIndex={4}
                                        disabled={processing}
                                        data-test="login-button"
                                    >
                                        {processing && <Spinner />}
                                        {t.submit}
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </>
    );
}
