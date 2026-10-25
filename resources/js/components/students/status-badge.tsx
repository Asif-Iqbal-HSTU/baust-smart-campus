import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const styles: Record<string, string> = {
    active: 'border-green-200 bg-green-50 text-green-700',
    inactive: 'border-slate-200 bg-slate-50 text-slate-600',
    admission_cancelled: 'border-rose-200 bg-rose-50 text-rose-700',
    graduated: 'border-blue-200 bg-blue-50 text-blue-700',
    dropped: 'border-red-200 bg-red-50 text-red-700',
    suspended: 'border-amber-200 bg-amber-50 text-amber-700',
    transferred: 'border-violet-200 bg-violet-50 text-violet-700',
    // Admission applications
    submitted: 'border-sky-200 bg-sky-50 text-sky-700',
    test_required: 'border-amber-200 bg-amber-50 text-amber-700',
    eligible: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    admitted: 'border-green-200 bg-green-50 text-green-700',
    rejected: 'border-rose-200 bg-rose-50 text-rose-700',
};

export default function StatusBadge({
    status,
    label,
}: {
    status: string;
    label?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn('whitespace-nowrap', styles[status] ?? '')}
        >
            {label ?? status.charAt(0).toUpperCase() + status.slice(1)}
        </Badge>
    );
}
