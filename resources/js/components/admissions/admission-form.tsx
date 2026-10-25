import { useForm } from '@inertiajs/react';
import {
    AlertCircle,
    BookOpen,
    CircleCheck,
    FileText,
    GraduationCap,
    Home,
    Paperclip,
    Plus,
    Trash2,
    User,
    Users,
    Wallet,
} from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectLabel,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type Option = { id: number | string; label: string };

export type AdmissionOptions = {
    programs: { department: string; programs: Option[] }[];
    quotas: Option[];
    boards: Option[];
    exams: { secondary: Option[]; higher_secondary: Option[] };
    genders: Option[];
    religions: Option[];
    blood_groups: Option[];
    marital_statuses: Option[];
    divisions: Option[];
    payment_methods: Option[];
    batches?: Option[];
    halls?: Option[];
};

type AddressData = {
    address_line: string;
    post_office: string;
    post_code: string;
    upazila: string;
    district: string;
    division: string;
};

type ResultData = {
    exam: string;
    board_id: string;
    passing_year: string;
    roll: string;
    registration_no: string;
    gpa: string;
    institute: string;
    group: string;
};

export type AdmissionFormData = {
    batch_id: string;
    hall_id: string;
    program_id: string;
    quota_id: string;
    name_en: string;
    name_bn: string;
    gender: string;
    date_of_birth: string;
    blood_group: string;
    religion: string;
    nationality: string;
    marital_status: string;
    nid: string;
    birth_registration_no: string;
    mobile: string;
    email: string;
    father_name: string;
    father_mobile: string;
    father_occupation: string;
    mother_name: string;
    mother_mobile: string;
    mother_occupation: string;
    guardian_name: string;
    guardian_relation: string;
    guardian_mobile: string;
    present: AddressData;
    permanent: AddressData;
    secondary: ResultData;
    higher_secondary: ResultData;
    payment_method: string;
    payment_reference: string;
    remarks: string;
    photo: File | null;
    documents: { name: string; file: File | null }[];
};

const emptyAddress: AddressData = {
    address_line: '',
    post_office: '',
    post_code: '',
    upazila: '',
    district: '',
    division: '',
};

const emptyResult = (exam: string): ResultData => ({
    exam,
    board_id: '',
    passing_year: '',
    roll: '',
    registration_no: '',
    gpa: '',
    institute: '',
    group: '',
});

/**
 * Turn server values (numbers, nulls) into the string-only form state.
 */
function normalize(values: Record<string, unknown>): Record<string, unknown> {
    return Object.fromEntries(
        Object.entries(values).map(([key, value]) => [
            key,
            value === null || value === undefined
                ? ''
                : typeof value === 'object'
                  ? normalize(value as Record<string, unknown>)
                  : String(value),
        ]),
    );
}

function blankForm(): AdmissionFormData {
    return {
        batch_id: '',
        hall_id: '',
        program_id: '',
        quota_id: '',
        name_en: '',
        name_bn: '',
        gender: '',
        date_of_birth: '',
        blood_group: '',
        religion: '',
        nationality: 'Bangladeshi',
        marital_status: 'single',
        nid: '',
        birth_registration_no: '',
        mobile: '',
        email: '',
        father_name: '',
        father_mobile: '',
        father_occupation: '',
        mother_name: '',
        mother_mobile: '',
        mother_occupation: '',
        guardian_name: '',
        guardian_relation: '',
        guardian_mobile: '',
        present: { ...emptyAddress },
        permanent: { ...emptyAddress },
        secondary: emptyResult('ssc'),
        higher_secondary: emptyResult('hsc'),
        payment_method: '',
        payment_reference: '',
        remarks: '',
        photo: null,
        documents: [],
    };
}

function Section({
    title,
    description,
    icon: Icon,
    children,
}: {
    title: string;
    description?: string;
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
                {description && (
                    <p className="text-sm text-muted-foreground">
                        {description}
                    </p>
                )}
            </CardHeader>
            <CardContent>{children}</CardContent>
        </Card>
    );
}

function Field({
    label,
    id,
    error,
    required,
    hint,
    className,
    children,
}: {
    label: string;
    id: string;
    error?: string;
    required?: boolean;
    hint?: string;
    className?: string;
    children: ReactNode;
}) {
    return (
        <div className={cn('grid content-start gap-1.5', className)}>
            <Label htmlFor={id}>
                {label}
                {required && <span className="text-destructive">*</span>}
            </Label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            <InputError message={error} />
        </div>
    );
}

function Choice({
    id,
    value,
    options,
    placeholder,
    onChange,
    invalid,
}: {
    id: string;
    value: string;
    options: Option[];
    placeholder: string;
    onChange: (value: string) => void;
    invalid?: boolean;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger id={id} className="w-full" aria-invalid={invalid}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.id} value={String(option.id)}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function GpaSummary({
    secondary,
    higher,
    threshold,
}: {
    secondary: string;
    higher: string;
    threshold: number;
}) {
    if (secondary === '' || higher === '') {
        return (
            <p className="text-sm text-muted-foreground">
                Applicants whose SSC + HSC GPA total is below{' '}
                {threshold.toFixed(2)} must sit the admission test.
            </p>
        );
    }

    const total = Number(secondary) + Number(higher);
    const needsTest = total < threshold;

    return (
        <Alert
            className={cn(
                needsTest
                    ? 'border-amber-200 bg-amber-50 text-amber-800'
                    : 'border-emerald-200 bg-emerald-50 text-emerald-800',
            )}
        >
            {needsTest ? <AlertCircle /> : <CircleCheck />}
            <AlertTitle>GPA total {total.toFixed(2)}</AlertTitle>
            <AlertDescription
                className={needsTest ? 'text-amber-800' : 'text-emerald-800'}
            >
                {needsTest
                    ? `Below ${threshold.toFixed(2)}: an admission test is required before admission.`
                    : `${threshold.toFixed(2)} or more: no admission test needed.`}
            </AlertDescription>
        </Alert>
    );
}

export default function AdmissionForm({
    mode,
    options,
    action,
    initial,
    testThreshold,
    submitLabel,
}: {
    mode: 'online' | 'staff';
    options: AdmissionOptions;
    action: { url: string; method: 'post' | 'put' };
    initial?: Record<string, unknown>;
    testThreshold: number;
    submitLabel: string;
}) {
    const staff = mode === 'staff';
    const editing = action.method === 'put';

    const form = useForm<AdmissionFormData>({
        ...blankForm(),
        ...(initial ? (normalize(initial) as Partial<AdmissionFormData>) : {}),
    });
    const { data, errors, processing } = form;
    const error = (key: string): string | undefined =>
        (errors as Record<string, string | undefined>)[key];

    const [sameAddress, setSameAddress] = useState(
        () =>
            JSON.stringify(data.present) === JSON.stringify(data.permanent) &&
            data.present.address_line !== '',
    );

    const set = <K extends keyof AdmissionFormData>(
        key: K,
        value: AdmissionFormData[K],
    ) => form.setData(key, value as never);

    const setNested = (
        group: 'present' | 'permanent' | 'secondary' | 'higher_secondary',
        key: string,
        value: string,
    ) =>
        form.setData(group, {
            ...(data[group] as Record<string, string>),
            [key]: value,
        } as never);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        form.transform((values) => ({
            ...values,
            permanent: sameAddress ? values.present : values.permanent,
            documents: values.documents.filter((document) => document.file),
            ...(editing ? { _method: 'put' } : {}),
        }));

        form.post(action.url, {
            forceFormData: true,
            preserveScroll: true,
            onError: () =>
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth',
                }),
        });
    };

    const errorCount = Object.keys(errors).length;

    const text = (
        key: keyof AdmissionFormData,
        label: string,
        props: {
            required?: boolean;
            type?: string;
            placeholder?: string;
            hint?: string;
            className?: string;
        } = {},
    ) => (
        <Field
            label={label}
            id={key}
            error={error(key)}
            required={props.required}
            hint={props.hint}
            className={props.className}
        >
            <Input
                id={key}
                type={props.type ?? 'text'}
                value={data[key] as string}
                placeholder={props.placeholder}
                onChange={(event) => set(key, event.target.value as never)}
                aria-invalid={Boolean(error(key))}
            />
        </Field>
    );

    const address = (type: 'present' | 'permanent') => (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Field
                label="Village / house / road"
                id={`${type}.address_line`}
                error={error(`${type}.address_line`)}
                required
                className="sm:col-span-2 lg:col-span-3"
            >
                <Input
                    id={`${type}.address_line`}
                    value={data[type].address_line}
                    onChange={(event) =>
                        setNested(type, 'address_line', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Post office"
                id={`${type}.post_office`}
                error={error(`${type}.post_office`)}
            >
                <Input
                    id={`${type}.post_office`}
                    value={data[type].post_office}
                    onChange={(event) =>
                        setNested(type, 'post_office', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Post code"
                id={`${type}.post_code`}
                error={error(`${type}.post_code`)}
            >
                <Input
                    id={`${type}.post_code`}
                    inputMode="numeric"
                    maxLength={4}
                    value={data[type].post_code}
                    onChange={(event) =>
                        setNested(type, 'post_code', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Upazila / thana"
                id={`${type}.upazila`}
                error={error(`${type}.upazila`)}
                required
            >
                <Input
                    id={`${type}.upazila`}
                    value={data[type].upazila}
                    onChange={(event) =>
                        setNested(type, 'upazila', event.target.value)
                    }
                />
            </Field>
            <Field
                label="District"
                id={`${type}.district`}
                error={error(`${type}.district`)}
                required
            >
                <Input
                    id={`${type}.district`}
                    value={data[type].district}
                    onChange={(event) =>
                        setNested(type, 'district', event.target.value)
                    }
                />
            </Field>
            <Field
                label="Division"
                id={`${type}.division`}
                error={error(`${type}.division`)}
                required
            >
                <Choice
                    id={`${type}.division`}
                    value={data[type].division}
                    options={options.divisions}
                    placeholder="Select division"
                    onChange={(value) => setNested(type, 'division', value)}
                    invalid={Boolean(error(`${type}.division`))}
                />
            </Field>
        </div>
    );

    const result = (level: 'secondary' | 'higher_secondary') => {
        const values = data[level];
        const noBoard = ['o_level', 'a_level'].includes(values.exam);
        const short = level === 'secondary' ? 'SSC' : 'HSC';
        const field = (key: keyof ResultData) => `${level}.${key}`;

        return (
            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Field
                    label="Examination"
                    id={field('exam')}
                    error={error(field('exam'))}
                    required
                >
                    <Choice
                        id={field('exam')}
                        value={values.exam}
                        options={options.exams[level]}
                        placeholder="Select exam"
                        onChange={(value) => setNested(level, 'exam', value)}
                    />
                </Field>
                <Field
                    label="Board"
                    id={field('board_id')}
                    error={error(field('board_id'))}
                    required={!noBoard}
                >
                    <Choice
                        id={field('board_id')}
                        value={values.board_id}
                        options={options.boards}
                        placeholder={
                            noBoard ? 'Not applicable' : 'Select board'
                        }
                        onChange={(value) =>
                            setNested(level, 'board_id', value)
                        }
                        invalid={Boolean(error(field('board_id')))}
                    />
                </Field>
                <Field
                    label="Passing year"
                    id={field('passing_year')}
                    error={error(field('passing_year'))}
                    required
                >
                    <Input
                        id={field('passing_year')}
                        inputMode="numeric"
                        maxLength={4}
                        value={values.passing_year}
                        onChange={(event) =>
                            setNested(level, 'passing_year', event.target.value)
                        }
                        aria-invalid={Boolean(error(field('passing_year')))}
                    />
                </Field>
                <Field
                    label={
                        values.exam === 'diploma'
                            ? 'CGPA (out of 4)'
                            : 'GPA (out of 5)'
                    }
                    id={field('gpa')}
                    error={error(field('gpa'))}
                    required
                >
                    <Input
                        id={field('gpa')}
                        type="number"
                        step="0.01"
                        min="1"
                        max={values.exam === 'diploma' ? '4' : '5'}
                        value={values.gpa}
                        onChange={(event) =>
                            setNested(level, 'gpa', event.target.value)
                        }
                        aria-invalid={Boolean(error(field('gpa')))}
                    />
                </Field>
                <Field
                    label="Roll"
                    id={field('roll')}
                    error={error(field('roll'))}
                    required
                >
                    <Input
                        id={field('roll')}
                        value={values.roll}
                        onChange={(event) =>
                            setNested(level, 'roll', event.target.value)
                        }
                        aria-invalid={Boolean(error(field('roll')))}
                    />
                </Field>
                <Field
                    label="Registration no."
                    id={field('registration_no')}
                    error={error(field('registration_no'))}
                >
                    <Input
                        id={field('registration_no')}
                        value={values.registration_no}
                        onChange={(event) =>
                            setNested(
                                level,
                                'registration_no',
                                event.target.value,
                            )
                        }
                    />
                </Field>
                <Field
                    label="Group"
                    id={field('group')}
                    error={error(field('group'))}
                    hint="Science, Business Studies, Humanities…"
                >
                    <Input
                        id={field('group')}
                        value={values.group}
                        onChange={(event) =>
                            setNested(level, 'group', event.target.value)
                        }
                    />
                </Field>
                <Field
                    label={level === 'secondary' ? 'School' : 'College'}
                    id={field('institute')}
                    error={error(field('institute'))}
                >
                    <Input
                        id={field('institute')}
                        value={values.institute}
                        placeholder={`${short} institute`}
                        onChange={(event) =>
                            setNested(level, 'institute', event.target.value)
                        }
                    />
                </Field>
            </div>
        );
    };

    return (
        <form onSubmit={submit} className="grid gap-4" noValidate>
            {(errorCount > 0 || error('application')) && (
                <Alert variant="destructive">
                    <AlertCircle />
                    <AlertTitle>
                        {error('application') ??
                            'Please correct the highlighted fields.'}
                    </AlertTitle>
                    {error('application') && errorCount > 1 && (
                        <AlertDescription>
                            Please also correct the highlighted fields.
                        </AlertDescription>
                    )}
                </Alert>
            )}

            <Section
                title="Program"
                icon={GraduationCap}
                description={
                    staff
                        ? 'The hall is part of the student ID. With a hall chosen, an applicant who needs no admission test is admitted on saving.'
                        : undefined
                }
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    {staff && options.batches && (
                        <Field
                            label="Batch"
                            id="batch_id"
                            error={error('batch_id')}
                            required
                        >
                            <Choice
                                id="batch_id"
                                value={data.batch_id}
                                options={options.batches}
                                placeholder="Select batch"
                                onChange={(value) => set('batch_id', value)}
                                invalid={Boolean(error('batch_id'))}
                            />
                        </Field>
                    )}
                    <Field
                        label="Program"
                        id="program_id"
                        error={error('program_id')}
                        required
                    >
                        <Select
                            value={data.program_id}
                            onValueChange={(value) => set('program_id', value)}
                        >
                            <SelectTrigger
                                id="program_id"
                                className="w-full"
                                aria-invalid={Boolean(error('program_id'))}
                            >
                                <SelectValue placeholder="Select program" />
                            </SelectTrigger>
                            <SelectContent>
                                {options.programs.map((group) => (
                                    <SelectGroup key={group.department}>
                                        <SelectLabel>
                                            {group.department}
                                        </SelectLabel>
                                        {group.programs.map((program) => (
                                            <SelectItem
                                                key={program.id}
                                                value={String(program.id)}
                                            >
                                                {program.label}
                                            </SelectItem>
                                        ))}
                                    </SelectGroup>
                                ))}
                            </SelectContent>
                        </Select>
                    </Field>
                    <Field
                        label="Quota"
                        id="quota_id"
                        error={error('quota_id')}
                    >
                        <Choice
                            id="quota_id"
                            value={data.quota_id}
                            options={options.quotas}
                            placeholder="General (no quota)"
                            onChange={(value) => set('quota_id', value)}
                        />
                    </Field>
                    {staff && options.halls && (
                        <Field
                            label="Hall"
                            id="hall_id"
                            error={error('hall_id')}
                        >
                            <Choice
                                id="hall_id"
                                value={data.hall_id}
                                options={options.halls}
                                placeholder="Select hall"
                                onChange={(value) => set('hall_id', value)}
                            />
                        </Field>
                    )}
                </div>
            </Section>

            <Section title="Applicant" icon={User}>
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {text('name_en', 'Full name (English)', {
                        required: true,
                        hint: 'As written on the SSC certificate.',
                        className: 'lg:col-span-2',
                    })}
                    {text('name_bn', 'নাম (বাংলায়)')}
                    <Field
                        label="Gender"
                        id="gender"
                        error={error('gender')}
                        required
                    >
                        <Choice
                            id="gender"
                            value={data.gender}
                            options={options.genders}
                            placeholder="Select gender"
                            onChange={(value) => set('gender', value)}
                            invalid={Boolean(error('gender'))}
                        />
                    </Field>
                    {text('date_of_birth', 'Date of birth', {
                        required: true,
                        type: 'date',
                    })}
                    <Field
                        label="Religion"
                        id="religion"
                        error={error('religion')}
                        required
                    >
                        <Choice
                            id="religion"
                            value={data.religion}
                            options={options.religions}
                            placeholder="Select religion"
                            onChange={(value) => set('religion', value)}
                            invalid={Boolean(error('religion'))}
                        />
                    </Field>
                    <Field
                        label="Blood group"
                        id="blood_group"
                        error={error('blood_group')}
                    >
                        <Choice
                            id="blood_group"
                            value={data.blood_group}
                            options={options.blood_groups}
                            placeholder="Select blood group"
                            onChange={(value) => set('blood_group', value)}
                        />
                    </Field>
                    {text('nationality', 'Nationality', { required: true })}
                    <Field
                        label="Marital status"
                        id="marital_status"
                        error={error('marital_status')}
                    >
                        <Choice
                            id="marital_status"
                            value={data.marital_status}
                            options={options.marital_statuses}
                            placeholder="Select"
                            onChange={(value) => set('marital_status', value)}
                        />
                    </Field>
                    {text('nid', 'NID number', {
                        hint: 'NID or birth registration number is required.',
                    })}
                    {text('birth_registration_no', 'Birth registration no.')}
                    {text('mobile', 'Mobile number', {
                        required: true,
                        type: 'tel',
                        placeholder: '01XXXXXXXXX',
                        hint: staff
                            ? undefined
                            : 'Used to check your application status.',
                    })}
                    {text('email', 'Email', { type: 'email' })}
                    <Field
                        label="Photo"
                        id="photo"
                        error={error('photo')}
                        required={!staff && !editing}
                        hint="Passport size, JPG or PNG, up to 2 MB."
                    >
                        <Input
                            id="photo"
                            type="file"
                            accept="image/jpeg,image/png"
                            onChange={(event) =>
                                set('photo', event.target.files?.[0] ?? null)
                            }
                            aria-invalid={Boolean(error('photo'))}
                        />
                    </Field>
                </div>
            </Section>

            <Section title="Parents and guardian" icon={Users}>
                <div className="grid gap-4 sm:grid-cols-3">
                    {text('father_name', "Father's name", { required: true })}
                    {text('father_mobile', "Father's mobile", { type: 'tel' })}
                    {text('father_occupation', "Father's occupation")}
                    {text('mother_name', "Mother's name", { required: true })}
                    {text('mother_mobile', "Mother's mobile", { type: 'tel' })}
                    {text('mother_occupation', "Mother's occupation")}
                    {text('guardian_name', 'Local guardian (if any)')}
                    {text('guardian_relation', 'Relation with guardian')}
                    {text('guardian_mobile', "Guardian's mobile", {
                        type: 'tel',
                    })}
                </div>
            </Section>

            <Section title="Academic results" icon={BookOpen}>
                <div className="grid gap-6">
                    <div className="grid gap-3">
                        <h3 className="text-sm font-medium">
                            SSC / Equivalent
                        </h3>
                        {result('secondary')}
                    </div>
                    <div className="grid gap-3">
                        <h3 className="text-sm font-medium">
                            HSC / Equivalent
                        </h3>
                        {result('higher_secondary')}
                    </div>
                    <GpaSummary
                        secondary={data.secondary.gpa}
                        higher={data.higher_secondary.gpa}
                        threshold={testThreshold}
                    />
                </div>
            </Section>

            <Section title="Address" icon={Home}>
                <div className="grid gap-6">
                    <div className="grid gap-3">
                        <h3 className="text-sm font-medium">Present address</h3>
                        {address('present')}
                    </div>
                    <div className="grid gap-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <h3 className="text-sm font-medium">
                                Permanent address
                            </h3>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={sameAddress}
                                    onCheckedChange={(checked) =>
                                        setSameAddress(checked === true)
                                    }
                                />
                                Same as present address
                            </label>
                        </div>
                        {!sameAddress && address('permanent')}
                    </div>
                </div>
            </Section>

            <Section
                title="Documents"
                icon={Paperclip}
                description="Scanned SSC and HSC certificates / mark sheets, testimonial, quota proof. JPG, PNG or PDF, up to 4 MB each."
            >
                <div className="grid gap-3">
                    {data.documents.map((document, index) => (
                        <div
                            key={index}
                            className="grid items-start gap-3 sm:grid-cols-[1fr_1fr_auto]"
                        >
                            <Field
                                label="Document"
                                id={`documents.${index}.name`}
                                error={error(`documents.${index}.name`)}
                            >
                                <Input
                                    id={`documents.${index}.name`}
                                    value={document.name}
                                    placeholder="e.g. HSC mark sheet"
                                    onChange={(event) =>
                                        set(
                                            'documents',
                                            data.documents.map((item, i) =>
                                                i === index
                                                    ? {
                                                          ...item,
                                                          name: event.target
                                                              .value,
                                                      }
                                                    : item,
                                            ),
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                label="File"
                                id={`documents.${index}.file`}
                                error={error(`documents.${index}.file`)}
                            >
                                <Input
                                    id={`documents.${index}.file`}
                                    type="file"
                                    accept="image/jpeg,image/png,application/pdf"
                                    onChange={(event) =>
                                        set(
                                            'documents',
                                            data.documents.map((item, i) =>
                                                i === index
                                                    ? {
                                                          ...item,
                                                          file:
                                                              event.target
                                                                  .files?.[0] ??
                                                              null,
                                                      }
                                                    : item,
                                            ),
                                        )
                                    }
                                />
                            </Field>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="sm:mt-6"
                                aria-label="Remove document"
                                onClick={() =>
                                    set(
                                        'documents',
                                        data.documents.filter(
                                            (_, i) => i !== index,
                                        ),
                                    )
                                }
                            >
                                <Trash2 />
                            </Button>
                        </div>
                    ))}
                    <InputError message={error('documents')} />
                    {data.documents.length < 6 && (
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() =>
                                    set('documents', [
                                        ...data.documents,
                                        { name: '', file: null },
                                    ])
                                }
                            >
                                <Plus />
                                Add document
                            </Button>
                        </div>
                    )}
                </div>
            </Section>

            <Section
                title={staff ? 'Payment and remarks' : 'Application fee'}
                icon={staff ? FileText : Wallet}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label="Payment method"
                        id="payment_method"
                        error={error('payment_method')}
                    >
                        <Choice
                            id="payment_method"
                            value={data.payment_method}
                            options={options.payment_methods}
                            placeholder="Select method"
                            onChange={(value) => set('payment_method', value)}
                        />
                    </Field>
                    {text('payment_reference', 'Transaction ID / receipt no.')}
                    {staff && (
                        <Field
                            label="Remarks"
                            id="remarks"
                            error={error('remarks')}
                            className="sm:col-span-2"
                        >
                            <Textarea
                                id="remarks"
                                value={data.remarks}
                                onChange={(event) =>
                                    set('remarks', event.target.value)
                                }
                            />
                        </Field>
                    )}
                </div>
            </Section>

            <div className="flex items-center justify-end gap-3">
                <Button type="submit" disabled={processing}>
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
