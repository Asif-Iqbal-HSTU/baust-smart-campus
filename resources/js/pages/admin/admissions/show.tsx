import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowLeft,
    BookOpen,
    CircleCheck,
    FileText,
    GraduationCap,
    History,
    Home,
    Pencil,
    ShieldCheck,
    Trash2,
    User,
    Users,
    XCircle,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import DeleteApplicationDialog from '@/components/admissions/delete-application-dialog';
import InputError from '@/components/input-error';
import StatusBadge from '@/components/students/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import {
    admit,
    document as documentRoute,
    edit,
    index,
    photo,
    reject,
    testResult,
    verify,
} from '@/routes/admin/admissions';
import { show as studentShow } from '@/routes/admin/students';
import type { Auth } from '@/types';

type Stamp = { at: string; by: string | null };

type Application = {
    id: number;
    application_no: string;
    source: string;
    source_label: string;
    status: string;
    status_label: string;
    submitted_at: string | null;
    batch: string;
    department: string;
    program: string;
    quota: string | null;
    hall_id: number | null;
    hall: string | null;
    name_en: string;
    name_bn: string | null;
    gender: string;
    date_of_birth: string;
    blood_group: string | null;
    religion: string | null;
    nationality: string;
    marital_status: string | null;
    nid: string | null;
    birth_registration_no: string | null;
    mobile: string;
    email: string | null;
    has_photo: boolean;
    guardians: {
        relation: string;
        name: string;
        mobile: string | null;
        occupation: string | null;
    }[];
    addresses: { type: string; text: string }[];
    educations: {
        level: string;
        exam: string;
        year: number;
        board: string | null;
        institute: string | null;
        group: string | null;
        roll: string;
        registration_no: string | null;
        gpa: string;
        gpa_scale: string;
    }[];
    gpa_total: string | null;
    needs_test: boolean;
    documents: { id: number; name: string; file: string | null }[];
    payment_method: string | null;
    payment_reference: string | null;
    remarks: string | null;
    verified: Stamp | null;
    test: (Stamp & { score: string | null; passed: boolean }) | null;
    rejected: (Stamp & { reason: string | null }) | null;
    admitted: (Stamp & { student_id: string | null }) | null;
};

type Option = { id: number; label: string };

type Props = {
    application: Application;
    matchingStudents: { student_id: string; name: string; status: string }[];
    halls: Option[];
    testThreshold: number;
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

function RejectDialog({ application }: { application: Application }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ reason: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(reject(application.application_no).url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" className="w-full">
                    <XCircle />
                    Reject
                </Button>
            </DialogTrigger>
            <DialogContent>
                <form onSubmit={submit} className="grid gap-4">
                    <DialogHeader>
                        <DialogTitle>Reject application</DialogTitle>
                        <DialogDescription>
                            {application.name_en} ({application.application_no})
                            will not be admitted. The reason is kept on record.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid gap-1.5">
                        <Label htmlFor="reason">Reason</Label>
                        <Textarea
                            id="reason"
                            value={form.data.reason}
                            onChange={(event) =>
                                form.setData('reason', event.target.value)
                            }
                            placeholder="e.g. Documents do not match the board result"
                        />
                        <InputError message={form.errors.reason} />
                    </div>
                    <DialogFooter>
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={form.processing}
                        >
                            {form.processing && <Spinner />}
                            Reject application
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function VerifyAction({
    application,
    testThreshold,
}: {
    application: Application;
    testThreshold: number;
}) {
    const form = useForm({});

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                Check the details, photo and documents against the board
                results, then verify.
            </p>
            <p className="text-sm">
                GPA total{' '}
                <span className="font-semibold">{application.gpa_total}</span>
                {application.needs_test
                    ? ` is below ${testThreshold.toFixed(2)}, so after verification the applicant goes to the admission test.`
                    : ` is ${testThreshold.toFixed(2)} or more, so after verification the applicant is ready to admit.`}
            </p>
            <Button
                disabled={form.processing}
                onClick={() =>
                    form.post(verify(application.application_no).url, {
                        preserveScroll: true,
                    })
                }
            >
                {form.processing ? <Spinner /> : <ShieldCheck />}
                Verify application
            </Button>
        </div>
    );
}

function TestResultAction({ application }: { application: Application }) {
    const form = useForm<{ score: string; passed: boolean | null }>({
        score: '',
        passed: null,
    });

    const record = (passed: boolean) => {
        form.transform((data) => ({ ...data, passed }));
        form.post(testResult(application.application_no).url, {
            preserveScroll: true,
        });
    };

    return (
        <div className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                GPA total {application.gpa_total} is below the threshold. Record
                the admission test result.
            </p>
            <div className="grid gap-1.5">
                <Label htmlFor="score">Test score (optional)</Label>
                <Input
                    id="score"
                    type="number"
                    step="0.01"
                    min="0"
                    value={form.data.score}
                    onChange={(event) =>
                        form.setData('score', event.target.value)
                    }
                />
                <InputError message={form.errors.score} />
            </div>
            <div className="grid grid-cols-2 gap-2">
                <Button disabled={form.processing} onClick={() => record(true)}>
                    <CircleCheck />
                    Passed
                </Button>
                <Button
                    variant="outline"
                    disabled={form.processing}
                    onClick={() => {
                        if (
                            confirm(
                                'Record a failed test? The application will be rejected.',
                            )
                        ) {
                            record(false);
                        }
                    }}
                >
                    <XCircle />
                    Failed
                </Button>
            </div>
        </div>
    );
}

function AdmitAction({
    application,
    halls,
}: {
    application: Application;
    halls: Option[];
}) {
    const form = useForm({
        hall_id: application.hall_id ? String(application.hall_id) : '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(admit(application.application_no).url, {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} className="grid gap-3">
            <p className="text-sm text-muted-foreground">
                Choose the hall; it is part of the student ID. A student record
                and login account are created.
            </p>
            <div className="grid gap-1.5">
                <Label htmlFor="hall_id">Hall</Label>
                <Select
                    value={form.data.hall_id}
                    onValueChange={(value) => form.setData('hall_id', value)}
                >
                    <SelectTrigger id="hall_id" className="w-full">
                        <SelectValue placeholder="Select hall" />
                    </SelectTrigger>
                    <SelectContent>
                        {halls.map((hall) => (
                            <SelectItem key={hall.id} value={String(hall.id)}>
                                {hall.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.hall_id} />
            </div>
            <Button
                type="submit"
                disabled={form.processing || !form.data.hall_id}
            >
                {form.processing ? <Spinner /> : <GraduationCap />}
                Admit and issue student ID
            </Button>
        </form>
    );
}

function NextStep({
    application,
    halls,
    testThreshold,
}: {
    application: Application;
    halls: Option[];
    testThreshold: number;
}) {
    const open = ['submitted', 'test_required', 'eligible'].includes(
        application.status,
    );

    return (
        <Card className="gap-4">
            <CardHeader>
                <CardTitle className="text-base">
                    {application.status === 'submitted' && 'Verify'}
                    {application.status === 'test_required' && 'Admission test'}
                    {application.status === 'eligible' && 'Admit'}
                    {application.status === 'admitted' && 'Admitted'}
                    {application.status === 'rejected' && 'Rejected'}
                </CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3">
                {application.status === 'submitted' && (
                    <VerifyAction
                        application={application}
                        testThreshold={testThreshold}
                    />
                )}
                {application.status === 'test_required' && (
                    <TestResultAction application={application} />
                )}
                {application.status === 'eligible' && (
                    <AdmitAction application={application} halls={halls} />
                )}
                {application.admitted && (
                    <div className="grid gap-2 text-sm">
                        <p>
                            Student ID{' '}
                            <span className="font-mono font-semibold">
                                {application.admitted.student_id}
                            </span>
                        </p>
                        {application.admitted.student_id && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={studentShow(
                                        application.admitted.student_id,
                                    )}
                                >
                                    Open student profile
                                </Link>
                            </Button>
                        )}
                    </div>
                )}
                {application.rejected && (
                    <p className="text-sm">
                        {application.rejected.reason ?? 'No reason given.'}
                    </p>
                )}
                {open && (
                    <div className="grid grid-cols-2 gap-2 border-t pt-3">
                        <Button variant="outline" asChild>
                            <Link href={edit(application.application_no)}>
                                <Pencil />
                                Edit
                            </Link>
                        </Button>
                        <RejectDialog application={application} />
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function AdmissionShow({
    application,
    matchingStudents,
    halls,
    testThreshold,
}: Props) {
    const { errors, auth } = usePage().props as {
        errors: Record<string, string>;
        auth: Auth;
    };
    const [deleting, setDeleting] = useState(false);

    const timeline = [
        {
            label: `Submitted (${application.source_label.toLowerCase()})`,
            at: application.submitted_at,
            by: null,
            detail: null,
        },
        application.verified && {
            label: 'Verified',
            ...application.verified,
            detail: null,
        },
        application.test && {
            label: application.test.passed
                ? 'Passed admission test'
                : 'Failed admission test',
            ...application.test,
            detail:
                application.test.score !== null
                    ? `Score ${application.test.score}`
                    : null,
        },
        application.admitted && {
            label: 'Admitted',
            ...application.admitted,
            detail: application.admitted.student_id,
        },
        application.rejected && {
            label: 'Rejected',
            ...application.rejected,
            detail: application.rejected.reason,
        },
    ].filter(Boolean) as {
        label: string;
        at: string | null;
        by: string | null;
        detail: string | null;
    }[];

    return (
        <>
            <Head
                title={`${application.name_en} · ${application.application_no}`}
            />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex items-start gap-4">
                        {application.has_photo ? (
                            <img
                                src={photo(application.application_no).url}
                                alt={application.name_en}
                                className="size-20 rounded-lg border object-cover"
                            />
                        ) : (
                            <div className="flex size-20 items-center justify-center rounded-lg border bg-muted">
                                <User className="size-8 text-muted-foreground" />
                            </div>
                        )}
                        <div className="grid gap-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="text-xl font-semibold tracking-tight">
                                    {application.name_en}
                                </h1>
                                <StatusBadge
                                    status={application.status}
                                    label={application.status_label}
                                />
                                <Badge variant="outline">
                                    {application.source_label}
                                </Badge>
                            </div>
                            {application.name_bn && (
                                <p className="text-sm">{application.name_bn}</p>
                            )}
                            <p className="font-mono text-sm text-muted-foreground">
                                {application.application_no}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={index()}>
                                <ArrowLeft />
                                All applications
                            </Link>
                        </Button>
                        {auth.user.role === 'admin' && (
                            <Button
                                variant="outline"
                                size="sm"
                                className="text-destructive"
                                onClick={() => setDeleting(true)}
                            >
                                <Trash2 />
                                Delete
                            </Button>
                        )}
                    </div>
                    <DeleteApplicationDialog
                        application={{
                            application_no: application.application_no,
                            name: application.name_en,
                            student_id:
                                application.admitted?.student_id ?? null,
                        }}
                        open={deleting}
                        onOpenChange={setDeleting}
                    />
                </div>

                {errors.application && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>{errors.application}</AlertTitle>
                    </Alert>
                )}

                {matchingStudents.length > 0 && (
                    <Alert className="border-amber-200 bg-amber-50 text-amber-800">
                        <AlertTriangle />
                        <AlertTitle>
                            Already a student with the same HSC roll, year and
                            board
                        </AlertTitle>
                        <AlertDescription className="text-amber-800">
                            <ul>
                                {matchingStudents.map((student) => (
                                    <li key={student.student_id}>
                                        <Link
                                            href={studentShow(
                                                student.student_id,
                                            )}
                                            className="font-mono underline"
                                        >
                                            {student.student_id}
                                        </Link>{' '}
                                        {student.name} ({student.status})
                                    </li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid items-start gap-4 lg:grid-cols-[1fr_320px]">
                    <div className="grid gap-4">
                        <Section title="Program" icon={GraduationCap}>
                            <dl className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label="Batch"
                                    value={application.batch}
                                />
                                <Field
                                    label="Program"
                                    value={application.program}
                                />
                                <Field
                                    label="Department"
                                    value={application.department}
                                />
                                <Field
                                    label="Quota"
                                    value={application.quota}
                                />
                                <Field label="Hall" value={application.hall} />
                            </dl>
                        </Section>

                        <Section title="Academic results" icon={BookOpen}>
                            <div className="grid gap-3">
                                <div className="overflow-x-auto rounded-lg border">
                                    <Table>
                                        <TableHeader className="bg-muted/50">
                                            <TableRow>
                                                <TableHead>Exam</TableHead>
                                                <TableHead>Board</TableHead>
                                                <TableHead>Year</TableHead>
                                                <TableHead>Roll</TableHead>
                                                <TableHead>Reg. no.</TableHead>
                                                <TableHead className="text-right">
                                                    GPA
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {application.educations.map(
                                                (education) => (
                                                    <TableRow
                                                        key={education.level}
                                                    >
                                                        <TableCell>
                                                            <div className="font-medium">
                                                                {education.exam}
                                                            </div>
                                                            <div className="text-xs text-muted-foreground">
                                                                {[
                                                                    education.group,
                                                                    education.institute,
                                                                ]
                                                                    .filter(
                                                                        Boolean,
                                                                    )
                                                                    .join(
                                                                        ' · ',
                                                                    )}
                                                            </div>
                                                        </TableCell>
                                                        <TableCell>
                                                            {education.board ??
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell>
                                                            {education.year}
                                                        </TableCell>
                                                        <TableCell className="font-mono text-xs">
                                                            {education.roll}
                                                        </TableCell>
                                                        <TableCell className="font-mono text-xs">
                                                            {education.registration_no ??
                                                                '—'}
                                                        </TableCell>
                                                        <TableCell className="text-right tabular-nums">
                                                            {education.gpa}
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                                <p
                                    className={
                                        application.needs_test
                                            ? 'text-sm text-amber-700'
                                            : 'text-sm text-emerald-700'
                                    }
                                >
                                    GPA total{' '}
                                    <span className="font-semibold">
                                        {application.gpa_total}
                                    </span>{' '}
                                    {application.needs_test
                                        ? `(below ${testThreshold.toFixed(2)}: admission test required)`
                                        : `(${testThreshold.toFixed(2)} or more: no admission test)`}
                                </p>
                            </div>
                        </Section>

                        <Section title="Applicant" icon={User}>
                            <dl className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label="Gender"
                                    value={application.gender}
                                />
                                <Field
                                    label="Date of birth"
                                    value={application.date_of_birth}
                                />
                                <Field
                                    label="Blood group"
                                    value={application.blood_group}
                                />
                                <Field
                                    label="Religion"
                                    value={application.religion}
                                />
                                <Field
                                    label="Nationality"
                                    value={application.nationality}
                                />
                                <Field
                                    label="Marital status"
                                    value={application.marital_status}
                                />
                                <Field label="NID" value={application.nid} />
                                <Field
                                    label="Birth registration"
                                    value={application.birth_registration_no}
                                />
                                <Field
                                    label="Mobile"
                                    value={application.mobile}
                                />
                                <Field
                                    label="Email"
                                    value={application.email}
                                />
                            </dl>
                        </Section>

                        <Section title="Parents and guardian" icon={Users}>
                            <dl className="grid gap-4 sm:grid-cols-3">
                                {application.guardians.map((guardian) => (
                                    <Field
                                        key={guardian.relation}
                                        label={guardian.relation}
                                        value={
                                            <>
                                                {guardian.name}
                                                <span className="block text-xs font-normal text-muted-foreground">
                                                    {[
                                                        guardian.mobile,
                                                        guardian.occupation,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </>
                                        }
                                    />
                                ))}
                            </dl>
                        </Section>

                        <Section title="Address" icon={Home}>
                            <dl className="grid gap-4 sm:grid-cols-2">
                                {application.addresses.map((address) => (
                                    <Field
                                        key={address.type}
                                        label={address.type}
                                        value={address.text}
                                    />
                                ))}
                            </dl>
                        </Section>

                        <Section title="Documents and payment" icon={FileText}>
                            <div className="grid gap-4">
                                {application.documents.length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        No documents uploaded.
                                    </p>
                                ) : (
                                    <ul className="grid gap-2 sm:grid-cols-2">
                                        {application.documents.map(
                                            (document) => (
                                                <li key={document.id}>
                                                    <a
                                                        href={
                                                            documentRoute([
                                                                application.application_no,
                                                                document.id,
                                                            ]).url
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="flex items-center gap-2 rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                                    >
                                                        <FileText className="size-4 text-muted-foreground" />
                                                        <span className="font-medium">
                                                            {document.name}
                                                        </span>
                                                        <span className="truncate text-xs text-muted-foreground">
                                                            {document.file}
                                                        </span>
                                                    </a>
                                                </li>
                                            ),
                                        )}
                                    </ul>
                                )}
                                <dl className="grid gap-4 sm:grid-cols-3">
                                    <Field
                                        label="Payment method"
                                        value={application.payment_method}
                                    />
                                    <Field
                                        label="Transaction / receipt"
                                        value={application.payment_reference}
                                    />
                                    <Field
                                        label="Remarks"
                                        value={application.remarks}
                                    />
                                </dl>
                            </div>
                        </Section>
                    </div>

                    <div className="grid gap-4 lg:sticky lg:top-4">
                        <NextStep
                            application={application}
                            halls={halls}
                            testThreshold={testThreshold}
                        />

                        <Section title="History" icon={History}>
                            <ol className="grid gap-3">
                                {timeline.map((entry) => (
                                    <li
                                        key={entry.label}
                                        className="grid gap-0.5 border-l-2 pl-3"
                                    >
                                        <span className="text-sm font-medium">
                                            {entry.label}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            {[entry.at, entry.by]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </span>
                                        {entry.detail && (
                                            <span className="text-xs">
                                                {entry.detail}
                                            </span>
                                        )}
                                    </li>
                                ))}
                            </ol>
                        </Section>
                    </div>
                </div>
            </div>
        </>
    );
}

AdmissionShow.layout = {
    breadcrumbs: [{ title: 'Admissions', href: index() }],
};
