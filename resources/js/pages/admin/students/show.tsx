import { Head, Link } from '@inertiajs/react';
import {
    ArrowLeft,
    BookOpen,
    GraduationCap,
    History,
    Home,
    User,
    Users,
} from 'lucide-react';
import type { ReactNode } from 'react';
import StatusBadge from '@/components/students/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { dashboard } from '@/routes';
import { index } from '@/routes/admin/students';

type Student = {
    id: number;
    student_id: string;
    registration_no: string | null;
    name_en: string;
    name_bn: string | null;
    status: string;
    status_label: string;
    status_history: {
        id: number;
        from: string | null;
        to: string;
        to_value: string;
        reason: string | null;
        remarks: string | null;
        by: string | null;
        at: string;
        source: string | null;
    }[];
    faculty: string;
    department: string;
    program: string;
    batch: string;
    hall: string | null;
    quota: string | null;
    gender: string | null;
    date_of_birth: string | null;
    blood_group: string | null;
    religion: string | null;
    nationality: string;
    nid: string | null;
    mobile: string | null;
    email: string | null;
    has_password: boolean;
    guardians: {
        relation: string;
        name: string;
        mobile: string | null;
        occupation: string | null;
        is_primary_contact: boolean;
    }[];
    addresses: { type: string; text: string }[];
    educations: {
        exam: string;
        year: number | null;
        board: string | null;
        institute: string | null;
        roll: string | null;
        gpa: string | null;
    }[];
};

function Field({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid gap-0.5">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd className="text-sm font-medium">
                {value ?? <span className="text-muted-foreground">—</span>}
            </dd>
        </div>
    );
}

function Section({
    title,
    icon: Icon,
    children,
}: {
    title: string;
    icon: typeof User;
    children: ReactNode;
}) {
    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Icon className="size-4 text-muted-foreground" />
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

export default function StudentShow({ student }: { student: Student }) {
    return (
        <>
            <Head title={`${student.name_en} · ${student.student_id}`} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="grid gap-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-xl font-semibold tracking-tight">
                                {student.name_en}
                            </h1>
                            <StatusBadge
                                status={student.status}
                                label={student.status_label}
                            />
                        </div>
                        {student.name_bn && (
                            <p className="text-sm">{student.name_bn}</p>
                        )}
                        <p className="font-mono text-sm text-muted-foreground">
                            {student.student_id}
                        </p>
                    </div>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={index()}>
                            <ArrowLeft />
                            All students
                        </Link>
                    </Button>
                </div>

                {student.status_history.length > 0 && (
                    <Section title="Status history" icon={History}>
                        <ol className="grid gap-3">
                            {student.status_history.map((change) => (
                                <li
                                    key={change.id}
                                    className="grid gap-1 rounded-lg border p-3 sm:grid-cols-[1fr_auto] sm:gap-4"
                                >
                                    <div className="grid gap-1">
                                        <div className="flex flex-wrap items-center gap-2 text-sm">
                                            {change.from && (
                                                <>
                                                    <span className="text-muted-foreground">
                                                        {change.from}
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        →
                                                    </span>
                                                </>
                                            )}
                                            <StatusBadge
                                                status={change.to_value}
                                                label={change.to}
                                            />
                                        </div>
                                        {change.reason && (
                                            <p className="text-sm">
                                                {change.reason}
                                            </p>
                                        )}
                                        {change.remarks && (
                                            <p className="text-sm text-muted-foreground">
                                                {change.remarks}
                                            </p>
                                        )}
                                    </div>
                                    <div className="grid content-start gap-0.5 text-xs text-muted-foreground sm:text-right">
                                        <span>{change.at}</span>
                                        {change.by && (
                                            <span>by {change.by}</span>
                                        )}
                                        {change.source && (
                                            <span>from {change.source}</span>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </Section>
                )}

                <div className="grid gap-4 lg:grid-cols-2">
                    <Section title="Academic" icon={GraduationCap}>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Field label="Program" value={student.program} />
                            <Field
                                label="Department"
                                value={student.department}
                            />
                            <Field label="Faculty" value={student.faculty} />
                            <Field label="Batch" value={student.batch} />
                            <Field label="Hall" value={student.hall} />
                            <Field label="Quota" value={student.quota} />
                            <Field
                                label="Registration no."
                                value={student.registration_no}
                            />
                            <Field
                                label="Portal access"
                                value={
                                    student.has_password ? (
                                        <Badge variant="secondary">
                                            Password issued
                                        </Badge>
                                    ) : (
                                        <Badge variant="outline">
                                            No password yet
                                        </Badge>
                                    )
                                }
                            />
                        </dl>
                    </Section>

                    <Section title="Personal" icon={User}>
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Field label="Gender" value={student.gender} />
                            <Field
                                label="Date of birth"
                                value={student.date_of_birth}
                            />
                            <Field
                                label="Blood group"
                                value={student.blood_group}
                            />
                            <Field label="Religion" value={student.religion} />
                            <Field
                                label="Nationality"
                                value={student.nationality}
                            />
                            <Field label="NID" value={student.nid} />
                            <Field label="Mobile" value={student.mobile} />
                            <Field label="Email" value={student.email} />
                        </dl>
                    </Section>

                    <Section title="Parents & guardians" icon={Users}>
                        {student.guardians.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No guardians recorded.
                            </p>
                        ) : (
                            <ul className="grid gap-3">
                                {student.guardians.map((guardian) => (
                                    <li
                                        key={`${guardian.relation}-${guardian.name}`}
                                        className="flex items-start justify-between gap-3 rounded-lg border p-3"
                                    >
                                        <div className="grid gap-0.5">
                                            <span className="text-sm font-medium">
                                                {guardian.name}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {guardian.relation}
                                                {guardian.occupation &&
                                                    ` · ${guardian.occupation}`}
                                            </span>
                                        </div>
                                        <div className="grid justify-items-end gap-1">
                                            <span className="font-mono text-xs">
                                                {guardian.mobile ?? '—'}
                                            </span>
                                            {guardian.is_primary_contact && (
                                                <Badge variant="secondary">
                                                    Primary contact
                                                </Badge>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Section>

                    <Section title="Addresses" icon={Home}>
                        {student.addresses.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No address recorded.
                            </p>
                        ) : (
                            <dl className="grid gap-4">
                                {student.addresses.map((address) => (
                                    <Field
                                        key={address.type}
                                        label={`${address.type} address`}
                                        value={address.text}
                                    />
                                ))}
                            </dl>
                        )}
                    </Section>
                </div>

                <Section title="Previous education" icon={BookOpen}>
                    {student.educations.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No exam results recorded.
                        </p>
                    ) : (
                        <div className="overflow-hidden rounded-lg border">
                            <Table>
                                <TableHeader className="bg-muted/50">
                                    <TableRow>
                                        <TableHead>Exam</TableHead>
                                        <TableHead>Year</TableHead>
                                        <TableHead>Board</TableHead>
                                        <TableHead>Institute</TableHead>
                                        <TableHead>Roll</TableHead>
                                        <TableHead className="text-right">
                                            GPA
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {student.educations.map((education) => (
                                        <TableRow key={education.exam}>
                                            <TableCell className="font-medium">
                                                {education.exam}
                                            </TableCell>
                                            <TableCell>
                                                {education.year ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {education.board ?? '—'}
                                            </TableCell>
                                            <TableCell>
                                                {education.institute ?? '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">
                                                {education.roll ?? '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-medium">
                                                {education.gpa ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </Section>
            </div>
        </>
    );
}

StudentShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Students', href: index() },
    ],
};
