import { Head } from '@inertiajs/react';
import type { AdmissionOptions } from '@/components/admissions/admission-form';
import AdmissionForm from '@/components/admissions/admission-form';
import Heading from '@/components/heading';
import { index, update } from '@/routes/admin/admissions';

export default function AdmissionEdit({
    application,
    options,
    testThreshold,
}: {
    application: {
        application_no: string;
        status_label: string;
        values: Record<string, unknown>;
    };
    options: AdmissionOptions;
    testThreshold: number;
}) {
    return (
        <>
            <Head title={`Edit ${application.application_no}`} />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
                <Heading
                    title={`Edit application ${application.application_no}`}
                    description={`Status: ${application.status_label}. A verified application is re-checked against the GPA rule when its results change. Leave the photo empty to keep the current one; new documents are added to the existing ones.`}
                />

                <AdmissionForm
                    mode="staff"
                    options={options}
                    action={{
                        url: update(application.application_no).url,
                        method: 'put',
                    }}
                    initial={application.values}
                    testThreshold={testThreshold}
                    submitLabel="Save changes"
                />
            </div>
        </>
    );
}

AdmissionEdit.layout = {
    breadcrumbs: [{ title: 'Admissions', href: index() }],
};
