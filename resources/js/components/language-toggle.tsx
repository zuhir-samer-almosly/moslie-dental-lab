import { Languages } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/hooks/use-language';
import { cn } from '@/lib/utils';

export default function LanguageToggle({
    className,
}: {
    className?: string;
}) {
    const { language, updateLanguage } = useLanguage();

    const toggleLanguage = () => {
        updateLanguage(language === 'ar' ? 'en' : 'ar');
    };

    return (
        <Button
            variant="outline"
            size="sm"
            onClick={toggleLanguage}
            className={cn('cursor-pointer font-medium', className)}
            aria-label="Toggle language"
        >
            <Languages className="mr-2 size-4" />
            {language === 'ar' ? 'English' : 'عربي'}
        </Button>
    );
}
