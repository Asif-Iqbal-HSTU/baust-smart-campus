import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search, Users, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import Heading from '@/components/heading';
import StatusBadge from '@/components/students/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import { dashboard } from '@/routes';
import { index, show } from '@/routes/admin/students';

type Option = { id: number | string; label: string };

type StudentRow = {
    id: number;
    student_id: string;
    name: string;
    department: string;
    program: string;
    batch: string;
    hall: string | null;
    quota: string | null;
    gender: string | null;
    mobile: string | null;
    status: string;
    status_label: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

type Filters = {
    search?: string;
    department?: string;
    program?: string;
    batch?: string;
    hall?: string;
    quota?: string;
    status?: string;
    gender?: string;
    per_page?: string;
};

type Props = {
    students: Paginated<StudentRow>;
    filters: Record<string, string | number>;
    statusCounts: Record<string, number>;
    options: {
        departments: (Option & { programs: Option[] })[];
        batches: Option[];
        halls: Option[];
        quotas: Option[];
        statuses: Option[];
        genders: Option[];
    };
};

const ALL = 'all';

function CountPill({ count }: { count: number }) {
    return (
        <span className="rounded-full bg-background/80 px-1.5 py-0.5 text-[11px] leading-none font-medium text-muted-foreground tabular-nums in-data-[state=active]:bg-brand/10 in-data-[state=active]:text-brand">
            {count.toLocaleString()}
        </span>
    );
}

function FilterSelect({
    value,
    placeholder,
    options,
    onChange,
    extra,
}: {
    value?: string;
    placeholder: string;
    options: Option[];
    onChange: (value: string | undefined) => void;
    extra?: Option[];
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
                {[...options, ...(extra ?? [])].map((option) => (
                    <SelectItem key={option.id} value={String(option.id)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export default function StudentsIndex({
    students,
    filters,
    statusCounts,
    options,
}: Props) {
    const [current, setCurrent] = useState<Filters>(() =>
        Object.fromEntries(
            Object.entries(filters).map(([key, value]) => [key, String(value)]),
        ),
    );
    const [search, setSearch] = useState(current.search ?? '');
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

    const update = (key: keyof Filters, value: string | undefined) => {
        const next = { ...current, [key]: value };

        // A program only makes sense inside its department.
        if (key === 'department') {
            next.program = undefined;
        }

        apply(next);
    };

    // Search as you type, after a short pause.
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

    const programs = useMemo(() => {
        const departments = current.department
            ? options.departments.filter(
                  (department) => String(department.id) === current.department,
              )
            : options.departments;

        return departments.flatMap((department) => department.programs);
    }, [current.department, options.departments]);

    const activeFilters = Object.keys(current).filter(
        (key) => key !== 'per_page',
    ).length;

    const allCount = Object.values(statusCounts).reduce(
        (sum, count) => sum + Number(count),
        0,
    );

    // Show every status that has students, plus the selected one.
    const statusTabs = options.statuses.filter(
        (status) =>
            Number(statusCounts[status.id] ?? 0) > 0 ||
            current.status === status.id,
    );

    return (
        <>
            <Head title="Students" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end justify-between gap-2">
                    <Heading
                        title="Students"
                        description="Search and filter every student of BAUST."
                    />
                </div>

                <Card className="py-4">
                    <CardContent className="grid gap-3 px-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div className="relative sm:col-span-2">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search by student ID, registration no., name or mobile"
                                className="pl-9"
                                aria-label="Search students"
                            />
                        </div>
                        <FilterSelect
                            value={current.department}
                            placeholder="All departments"
                            options={options.departments}
                            onChange={(value) => update('department', value)}
                        />
                        <FilterSelect
                            value={current.program}
                            placeholder="All programs"
                            options={programs}
                            onChange={(value) => update('program', value)}
                        />
                        <FilterSelect
                            value={current.batch}
                            placeholder="All batches"
                            options={options.batches}
                            onChange={(value) => update('batch', value)}
                        />
                        <FilterSelect
                            value={current.hall}
                            placeholder="All halls"
                            options={options.halls}
                            extra={[{ id: 'none', label: 'No hall recorded' }]}
                            onChange={(value) => update('hall', value)}
                        />
                        <FilterSelect
                            value={current.quota}
                            placeholder="All quotas"
                            options={options.quotas}
                            onChange={(value) => update('quota', value)}
                        />
                        <FilterSelect
                            value={current.gender}
                            placeholder="Any gender"
                            options={options.genders}
                            onChange={(value) => update('gender', value)}
                        />
                    </CardContent>
                </Card>

                <div className="-mx-1 overflow-x-auto px-1">
                    <Tabs
                        value={current.status ?? ALL}
                        onValueChange={(value) =>
                            update('status', value === ALL ? undefined : value)
                        }
                    >
                        <TabsList aria-label="Filter by status">
                            <TabsTrigger value={ALL} className="gap-2 px-3">
                                All
                                <CountPill count={allCount} />
                            </TabsTrigger>
                            {statusTabs.map((status) => (
                                <TabsTrigger
                                    key={status.id}
                                    value={String(status.id)}
                                    className="gap-2 px-3"
                                >
                                    {status.label}
                                    <CountPill
                                        count={Number(
                                            statusCounts[status.id] ?? 0,
                                        )}
                                    />
                                </TabsTrigger>
                            ))}
                        </TabsList>
                    </Tabs>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div className="flex items-center gap-2 text-muted-foreground">
                        <Users className="size-4" />
                        <span>
                            <span className="font-medium text-foreground">
                                {students.total.toLocaleString()}
                            </span>{' '}
                            {students.total === 1 ? 'student' : 'students'}
                            {activeFilters > 0 && ' match your filters'}
                        </span>
                    </div>
                    {activeFilters > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setSearch('');
                                apply({ per_page: current.per_page });
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
                                <TableHead>Student ID</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Program</TableHead>
                                <TableHead>Batch</TableHead>
                                <TableHead className="hidden 2xl:table-cell">
                                    Hall
                                </TableHead>
                                <TableHead className="hidden lg:table-cell">
                                    Quota
                                </TableHead>
                                <TableHead className="hidden 2xl:table-cell">
                                    Mobile
                                </TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.data.length === 0 && (
                                <TableRow>
                                    <TableCell
                                        colSpan={8}
                                        className="h-32 text-center text-muted-foreground"
                                    >
                                        No students match these filters.
                                    </TableCell>
                                </TableRow>
                            )}
                            {students.data.map((student) => (
                                <TableRow
                                    key={student.id}
                                    className="cursor-pointer"
                                    onClick={() =>
                                        router.visit(show(student.student_id))
                                    }
                                >
                                    <TableCell className="font-mono text-xs">
                                        <Link
                                            href={show(student.student_id)}
                                            className="hover:underline"
                                            onClick={(event) =>
                                                event.stopPropagation()
                                            }
                                        >
                                            {student.student_id}
                                        </Link>
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {student.name}
                                    </TableCell>
                                    <TableCell>
                                        <div className="text-sm">
                                            {student.program}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {student.department}
                                        </div>
                                    </TableCell>
                                    <TableCell className="whitespace-nowrap">
                                        {student.batch}
                                    </TableCell>
                                    <TableCell className="hidden max-w-44 truncate 2xl:table-cell">
                                        {student.hall ?? (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </TableCell>
                                    <TableCell className="hidden lg:table-cell">
                                        {student.quota ?? '—'}
                                    </TableCell>
                                    <TableCell className="hidden font-mono text-xs 2xl:table-cell">
                                        {student.mobile ?? '—'}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge
                                            status={student.status}
                                            label={student.status_label}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <div className="flex items-center gap-2 text-muted-foreground">
                        Rows per page
                        <Select
                            value={String(students.per_page)}
                            onValueChange={(value) =>
                                apply({ ...current, per_page: value })
                            }
                        >
                            <SelectTrigger size="sm" className="w-20">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {['25', '50', '100'].map((size) => (
                                    <SelectItem key={size} value={size}>
                                        {size}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="flex items-center gap-3">
                        <span className="text-muted-foreground">
                            {students.from ?? 0}–{students.to ?? 0} of{' '}
                            {students.total.toLocaleString()} · page{' '}
                            {students.current_page} of {students.last_page}
                        </span>
                        <Button
                            variant="outline"
                            size="icon"
                            disabled={!students.prev_page_url}
                            asChild={!!students.prev_page_url}
                            aria-label="Previous page"
                        >
                            {students.prev_page_url ? (
                                <Link
                                    href={students.prev_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    <ChevronLeft />
                                </Link>
                            ) : (
                                <ChevronLeft />
                            )}
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            disabled={!students.next_page_url}
                            asChild={!!students.next_page_url}
                            aria-label="Next page"
                        >
                            {students.next_page_url ? (
                                <Link
                                    href={students.next_page_url}
                                    preserveScroll
                                    preserveState
                                >
                                    <ChevronRight />
                                </Link>
                            ) : (
                                <ChevronRight />
                            )}
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}

StudentsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Students', href: index() },
    ],
};
