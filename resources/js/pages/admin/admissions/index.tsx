import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    ClipboardList,
    Eye,
    Globe,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Trash2,
    X,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import DeleteApplicationDialog from '@/components/admissions/delete-application-dialog';
import Heading from '@/components/heading';
import StatusBadge from '@/components/students/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { close, next, open } from '@/routes/admin/admission-intake';
import { create, edit, index, show } from '@/routes/admin/admissions';
import type { Auth } from '@/types';

type Option = { id: number | string; label: string };

type ApplicationRow = {
    id: number;
    application_no: string;
    name: string;
    mobile: string;
    program: string;
    batch: string;
    gpa_total: string | null;
    needs_test: boolean;
    source: string;
    status: string;
    status_label: string;
    student_id: string | null;
    submitted_at: string | null;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    search?: string;
    batch?: string;
    program?: string;
    source?: string;
    status?: string;
};

type Props = {
    applications: Paginated<ApplicationRow>;
    filters: Record<string, string | number>;
    statusCounts: Record<string, number>;
    intake: {
        open: { id: number; label: string } | null;
        batches: Option[];
        next: string | null;
    };
    testThreshold: number;
    options: {
        batches: Option[];
        programs: Option[];
        sources: Option[];
        statuses: Option[];
    };
};

const ALL = 'all';

const OPEN_STATUSES = ['submitted', 'test_required', 'eligible'];

function FilterSelect({
    value,
    placeholder,
    options,
    onChange,
}: {
    value?: string;
    placeholder: string;
    options: Option[];
    onChange: (value: string | undefined) => void;
}) {
    return (
        <Select
            value={value ?? ALL}
            onValueChange={(next) => onChange(next === ALL ? undefined : next)}
        >
            <SelectTrigger className="w-full">
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value={ALL}>{placeholder}</SelectItem>
                {options.map((option) => (
                    <SelectItem key={option.id} value={String(option.id)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function IntakeCard({ intake }: { intake: Props['intake'] }) {
    const [batch, setBatch] = useState<string>(
        String(intake.batches[0]?.id ?? ''),
    );

    return (
        <Card className="py-4">
            <CardContent className="flex flex-wrap items-center justify-between gap-3 px-4">
                <div className="flex items-center gap-3">
                    <Globe className="size-5 text-muted-foreground" />
                    <div>
                        <p className="text-sm font-medium">Online admission</p>
                        <p className="text-sm text-muted-foreground">
                            {intake.open
                                ? `Open for the ${intake.open.label} batch at /apply`
                                : 'Closed: the public form does not accept applications.'}
                        </p>
                    </div>
                </div>
                {intake.open ? (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() =>
                            router.post(close(), {}, { preserveScroll: true })
                        }
                    >
                        Close online admission
                    </Button>
                ) : (
                    <div className="flex flex-wrap items-center gap-2">
                        <Select value={batch} onValueChange={setBatch}>
                            <SelectTrigger className="w-52">
                                <SelectValue placeholder="Batch" />
                            </SelectTrigger>
                            <SelectContent>
                                {intake.batches.map((option) => (
                                    <SelectItem
                                        key={option.id}
                                        value={String(option.id)}
                                    >
                                        {option.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <Button
                            size="sm"
                            disabled={!batch}
                            onClick={() =>
                                router.post(
                                    open(Number(batch)),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            Open
                        </Button>
                        {intake.next && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (
                                        confirm(
                                            `Create the ${intake.next} batch and open it for online admission?`,
                                        )
                                    ) {
                                        router.post(
                                            next(),
                                            {},
                                            { preserveScroll: true },
                                        );
                                    }
                                }}
                            >
                                <Plus />
                                New batch {intake.next}
                            </Button>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdmissionsIndex({
    applications,
    filters,
    statusCounts,
    intake,
    testThreshold,
    options,
}: Props) {
    const [current, setCurrent] = useState<Filters>(() =>
        Object.fromEntries(
            Object.entries(filters).map(([key, value]) => [key, String(value)]),
        ),
    );
    const [search, setSearch] = useState(current.search ?? '');
    const [deleting, setDeleting] = useState<ApplicationRow | null>(null);
    const { auth } = usePage<{ auth: Auth }>().props;
    const isAdmin = auth.user.role === 'admin';
    const firstRender = useRef(true);

    const apply = (next: Filters) => {
        const cleaned = Object.fromEntries(
            Object.entries(next).filter(([, value]) => value),
        );
        setCurrent(cleaned);
        router.get(index().url, cleaned, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const update = (key: keyof Filters, value: string | undefined) =>
        apply({ ...current, [key]: value });

    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;

            return;
        }

        const timer = setTimeout(() => {
            if ((current.search ?? '') !== search.trim()) {
                apply({ ...current, search: search.trim() || undefined });
            }
        }, 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const allCount = Object.values(statusCounts).reduce(
        (sum, count) => sum + Number(count),
        0,
    );
    const activeFilters = Object.keys(current).length;

    return (
        <>
            <Head title="Admissions" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <Heading
                        title="Admissions"
                        description={`Verify online applications, record admission tests (GPA total below ${testThreshold.toFixed(2)}) and admit students.`}
                    />
                    <Button asChild>
                        <Link href={create()}>
                            <Plus />
                            Office admission
                        </Link>
                    </Button>
                </div>

                <IntakeCard intake={intake} />

                <Card className="py-4">
                    <CardContent className="grid gap-3 px-4 sm:grid-cols-2 lg:grid-cols-5">
                        <div className="relative sm:col-span-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Application no., name, mobile or SSC/HSC roll"
                                className="pl-9"
                                aria-label="Search applications"
                            />
                        </div>
                        <FilterSelect
                            value={current.batch}
                            placeholder="All batches"
                            options={options.batches}
                            onChange={(value) => update('batch', value)}
                        />
                        <FilterSelect
                            value={current.program}
                            placeholder="All programs"
                            options={options.programs}
                            onChange={(value) => update('program', value)}
                        />
                        <FilterSelect
                            value={current.source}
                            placeholder="Online and office"
                            options={options.sources}
                            onChange={(value) => update('source', value)}
                        />
                    </CardContent>
                </Card>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="-mx-1 overflow-x-auto px-1">
                        <Tabs
                            value={current.status ?? ALL}
                            onValueChange={(value) =>
                                update(
                                    'status',
                                    value === ALL ? undefined : value,
                                )
                            }
                        >
                            <TabsList aria-label="Filter by status">
                                <TabsTrigger value={ALL} className="gap-2 px-3">
                                    All
                                    <span className="text-xs text-muted-foreground tabular-nums">
                                        {allCount}
                                    </span>
                                </TabsTrigger>
                                {options.statuses.map((status) => (
                                    <TabsTrigger
                                        key={status.id}
                                        value={String(status.id)}
                                        className="gap-2 px-3"
                                    >
                                        {status.label}
                                        <span className="text-xs text-muted-foreground tabular-nums">
                                            {Number(
                                                statusCounts[status.id] ?? 0,
                                            )}
                                        </span>
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </Tabs>
                    </div>
                    {activeFilters > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                apply({});
                            }}
                        >
                            <X />
                            Clear filters
                        </Button>
                    )}
                </div>

                <div className="overflow-hidden rounded-xl border">
                    <Table>
                        <TableHeader className="bg-muted/50">
                            <TableRow>
                                <TableHead>Application</TableHead>
                                <TableHead>Applicant</TableHead>
                                <TableHead>Program</TableHead>
                                <TableHead className="text-right">
                                    SSC + HSC
                                </TableHead>
                                <TableHead className="hidden md:table-cell">
                                    Source
                                </TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="w-10">
                                    <span className="sr-only">Actions</span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {applications.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={7}
                                        className="h-32 text-center text-muted-foreground"
                                    >
                                        <ClipboardList className="mx-auto mb-2 size-6" />
                                        No applications match these filters.
                                    </TableCell>
                                </TableRow>
                            )}
                            {applications.data.map((application) => (
                                <TableRow
                                    key={application.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(
                                            show(application.application_no),
                                        )
                                    }
                                >
                                    <TableCell>
                                        <Link
                                            href={show(
                                                application.application_no,
                                            )}
                                            className="font-mono text-xs hover:underline"
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                        >
                                            {application.application_no}
                                        </Link>
                                        <div className="text-xs text-muted-foreground">
                                            {application.submitted_at}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="font-medium">
                                            {application.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {application.mobile}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="text-sm">
                                            {application.program}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {application.batch} batch
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <div className="font-medium tabular-nums">
                                            {application.gpa_total ?? '—'}
                                        </div>
                                        {application.needs_test && (
                                            <div className="text-xs text-amber-700">
                                                Test required
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell className="hidden md:table-cell">
                                        <Badge variant="outline">
                                            {application.source === 'online'
                                                ? 'Online'
                                                : 'Office'}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={application.status}
                                            label={application.status_label}
                                        />
                                        {application.student_id && (
                                            <div className="mt-1 font-mono text-xs text-muted-foreground">
                                                {application.student_id}
                                            </div>
                                        )}
                                    </TableCell>
                                    <TableCell
                                        onClick={(event) =>
                                            event.stopPropagation()
                                        }
                                    >
                                        <DropdownMenu>
                                            <DropdownMenuTrigger asChild>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="size-8"
                                                    aria-label={`Actions for ${application.application_no}`}
                                                >
                                                    <MoreHorizontal />
                                                </Button>
                                            </DropdownMenuTrigger>
                                            <DropdownMenuContent align="end">
                                                <DropdownMenuItem asChild>
                                                    <Link
                                                        href={show(
                                                            application.application_no,
                                                        )}
                                                    >
                                                        <Eye />
                                                        View
                                                    </Link>
                                                </DropdownMenuItem>
                                                {OPEN_STATUSES.includes(
                                                    application.status,
                                                ) && (
                                                    <DropdownMenuItem asChild>
                                                        <Link
                                                            href={edit(
                                                                application.application_no,
                                                            )}
                                                        >
                                                            <Pencil />
                                                            Edit
                                                        </Link>
                                                    </DropdownMenuItem>
                                                )}
                                                {isAdmin && (
                                                    <>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem
                                                            variant="destructive"
                                                            onSelect={() =>
                                                                setDeleting(
                                                                    application,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 />
                                                            Delete
                                                        </DropdownMenuItem>
                                                    </>
                                                )}
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                {deleting && (
                    <DeleteApplicationDialog
                        application={deleting}
                        open
                        onOpenChange={(open) => !open && setDeleting(null)}
                    />
                )}

                {applications.last_page > 1 && (
                    <div className="flex items-center justify-between gap-2 text-sm text-muted-foreground">
                        <span>
                            {applications.from}–{applications.to} of{' '}
                            {applications.total}
                        </span>
                        <div className="flex gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!applications.prev_page_url}
                                onClick={() =>
                                    applications.prev_page_url &&
                                    router.visit(applications.prev_page_url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                <ChevronLeft />
                                Previous
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                disabled={!applications.next_page_url}
                                onClick={() =>
                                    applications.next_page_url &&
                                    router.visit(applications.next_page_url, {
                                        preserveScroll: true,
                                    })
                                }
                            >
                                Next
                                <ChevronRight />
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

AdmissionsIndex.layout = {
    breadcrumbs: [{ title: 'Admissions', href: index() }],
};
