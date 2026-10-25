import { Head, Link } from '@inertiajs/react';
import { CircleCheck } from 'lucide-react';
import PublicShell from '@/components/admissions/public-shell';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { status } from '@/routes/apply';

export default function ApplySubmitted({
    applicationNo,
}: {
    applicationNo: string;
}) {
    return (
        <PublicShell>
            <Head title="Application submitted" />

            <Card className="mx-auto max-w-xl">
                <CardContent className="flex flex-col items-center gap-4 py-10 text-center">
                    <CircleCheck className="size-10 text-brand" />
                    <div className="grid gap-1">
                        <h1 className="text-xl font-semibold">
                            Application submitted
                        </h1>
                        <p className="text-muted-foreground">
                            Your application number is
                        </p>
                        <p className="font-mono text-2xl font-semibold tracking-wider">
                            {applicationNo}
                        </p>
                    </div>
                    <p className="max-w-md text-sm text-muted-foreground">
                        Write this number down. The admission office will verify
                        your application; you can follow its progress with this
                        number and your mobile number.
                    </p>
                    <Button asChild>
                        <Link
                            href={status({
                                query: { application_no: applicationNo },
                            })}
                        >
                            Check application status
                        </Link>
                    </Button>
                </CardContent>
            </Card>
        </PublicShell>
    );
}
