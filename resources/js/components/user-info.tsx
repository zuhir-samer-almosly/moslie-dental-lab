import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    subtitle,
}: {
    user: User;
    showEmail?: boolean;
    subtitle?: string;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-9 w-9 overflow-hidden rounded-full">
                <AvatarImage src={user.avatar} alt={user.name} />
                <AvatarFallback className="rounded-full bg-secondary font-bold text-secondary-foreground">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            {/* pb/-mb: Arabic descenders (the dots under a final ي) fall
                outside the tight line box and `truncate` clips them. */}
            <div className="grid flex-1 text-right text-sm leading-tight">
                <span className="-mb-1 truncate pb-1 font-semibold">
                    {user.name}
                </span>
                {showEmail && (
                    <span className="-mb-1 truncate pb-1 text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
                {!showEmail && subtitle && (
                    <span className="-mb-1 truncate pb-1 text-xs text-muted-foreground">
                        {subtitle}
                    </span>
                )}
            </div>
        </>
    );
}
