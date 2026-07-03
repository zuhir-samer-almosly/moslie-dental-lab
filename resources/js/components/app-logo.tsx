export default function AppLogo() {
    return (
        <>
            <div className="flex aspect-square size-9 items-center justify-center rounded-xl bg-primary text-primary-foreground">
                {/* Tooth glyph (lucide has no tooth icon). */}
                <svg
                    width="22"
                    height="22"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="2"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden
                >
                    <path d="M12 5.5c-1.5-1.8-4-2.3-6-1-2.3 1.5-2.6 4.7-1.2 7 1 1.6 1.5 3.4 1.7 5.3.2 1.6.5 3.2 1.5 3.2 1.6 0 1.2-3.6 4-3.6s2.4 3.6 4 3.6c1 0 1.3-1.6 1.5-3.2.2-1.9.7-3.7 1.7-5.3 1.4-2.3 1.1-5.5-1.2-7-2-1.3-4.5-.8-6 1z" />
                </svg>
            </div>
            <div className="grid flex-1 text-right leading-tight">
                <span className="truncate text-[15px] font-bold text-foreground">
                    مخبر الموصلي
                </span>
                <span className="truncate text-xs text-muted-foreground">
                    إدارة مالية المختبر
                </span>
            </div>
        </>
    );
}
