import { Form, Head } from '@inertiajs/react';
import { SearchX } from 'lucide-react';
import PublicShell from '@/components/admissions/public-shell';
import StatusBadge from '@/components/students/status-badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { status } from '@/routes/apply';

type Result = {
    application_no: string;
    name: string;
    status: string;
    status_label: string;
    message: string;
};

export default function ApplyStatus({
    lookup,
    result,
}: {
    lookup: { application_no?: string; mobile?: string };
    result: Result | false | null;
}) {
    return (
        <PublicShell>
            <Head title="Application status" />

            <div className="mx-auto grid max-w-xl gap-4">
                <Card>
                    <CardHeader>
                        <CardTitle>Check application status</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Form
                            {...status.form()}
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ processing }) => (
                                <>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="application_no">
                                            Application number
                                        </Label>
                                        <Input
                                            id="application_no"
                                            name="application_no"
                                            defaultValue={lookup.application_no}
                                            placeholder="ADM2400012"
                                            required
                                        />
                                    </div>
                                    <div className="grid gap-1.5">
                                        <Label htmlFor="mobile">
                                            Mobile number
                                        </Label>
                                        <Input
                                            id="mobile"
                                            name="mobile"
                                            type="tel"
                                            defaultValue={lookup.mobile}
                                            placeholder="01XXXXXXXXX"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Button disabled={processing}>
                                            Check status
                                        </Button>
                                    </div>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>

                {result === false && (
                    <Card>
                        <CardContent className="flex items-center gap-3 text-sm">
                            <SearchX className="size-5 shrink-0 text-muted-foreground" />
                            No application matches this number and mobile
                            number.
                        </CardContent>
                    </Card>
                )}

                {result && (
                    <Card>
                        <CardContent className="grid gap-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <div>
                                    <p className="font-medium">{result.name}</p>
                                    <p className="font-mono text-sm text-muted-foreground">
                                        {result.application_no}
                                    </p>
                                </div>
                                <StatusBadge
                                    status={result.status}
                                    label={result.status_label}
                                />
                            </div>
                            <p className="text-sm">{result.message}</p>
                        </CardContent>
                    </Card>
                )}
            </div>
        </PublicShell>
    );
}
