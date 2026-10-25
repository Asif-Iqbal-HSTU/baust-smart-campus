import { Head } from '@inertiajs/react';
import type { AdmissionOptions } from '@/components/admissions/admission-form';
import AdmissionForm from '@/components/admissions/admission-form';
import Heading from '@/components/heading';
import { create, index, store } from '@/routes/admin/admissions';

export default function AdmissionCreate({
    options,
    defaultBatch,
    testThreshold,
}: {
    options: AdmissionOptions;
    defaultBatch: number | null;
    testThreshold: number;
}) {
    return (
        <>
            <Head title="Office admission" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-4 p-4">
                <Heading
                    title="Office admission"
                    description="Enter a paper admission form. It counts as verified by you; applicants with a GPA total below the threshold go to the admission test first."
                />

                <AdmissionForm
                    mode="staff"
                    options={options}
                    action={{ url: store().url, method: 'post' }}
                    initial={{ batch_id: defaultBatch }}
                    testThreshold={testThreshold}
                    submitLabel="Save application"
                />
            </div>
        </>
    );
}

AdmissionCreate.layout = {
    breadcrumbs: [
        { title: 'Admissions', href: index() },
        { title: 'Office admission', href: create() },
    ],
};
