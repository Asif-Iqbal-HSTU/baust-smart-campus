import { Head } from '@inertiajs/react';
import { CalendarX2, Info } from 'lucide-react';
import type { AdmissionOptions } from '@/components/admissions/admission-form';
import AdmissionForm from '@/components/admissions/admission-form';
import PublicShell from '@/components/admissions/public-shell';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent } from '@/components/ui/card';
import { store } from '@/routes/apply';

export default function ApplyCreate({
    intake,
    options,
    testThreshold,
}: {
    intake: string | null;
    options: AdmissionOptions | null;
    testThreshold: number;
}) {
    return (
        <PublicShell>
            <Head title="Online admission" />

            <div className="grid gap-6">
                <div className="grid gap-1">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Online admission
                    </h1>
                    <p className="text-muted-foreground">
                        {intake
                            ? `Apply for the ${intake} batch.`
                            : 'Apply to BAUST from anywhere.'}
                    </p>
                </div>

                {options === null ? (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-2 py-12 text-center">
                            <CalendarX2 className="size-8 text-muted-foreground" />
                            <p className="font-medium">
                                Online admission is closed at the moment.
                            </p>
                            <p className="max-w-md text-sm text-muted-foreground">
                                Please check back when the next admission
                                circular is published, or visit the admission
                                office.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <>
                        <Alert>
                            <Info />
                            <AlertTitle>Before you start</AlertTitle>
                            <AlertDescription>
                                <ul className="list-disc space-y-1 pl-4">
                                    <li>
                                        Keep your SSC and HSC results, a
                                        passport-size photo and scanned
                                        certificates ready.
                                    </li>
                                    <li>
                                        The admission office verifies every
                                        application before admission.
                                    </li>
                                    <li>
                                        If your SSC + HSC GPA total is below{' '}
                                        {testThreshold.toFixed(2)}, you will
                                        need to sit the admission test.
                                    </li>
                                </ul>
                            </AlertDescription>
                        </Alert>

                        <AdmissionForm
                            mode="online"
                            options={options}
                            action={{ url: store().url, method: 'post' }}
                            testThreshold={testThreshold}
                            submitLabel="Submit application"
                        />
                    </>
                )}
            </div>
        </PublicShell>
    );
}
