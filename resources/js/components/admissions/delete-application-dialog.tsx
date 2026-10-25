import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import { destroy } from '@/routes/admin/admissions';

/**
 * Confirm deleting an application. An admitted one also deletes the student
 * record and login it created, so say so plainly.
 */
export default function DeleteApplicationDialog({
    application,
    open,
    onOpenChange,
}: {
    application: {
        application_no: string;
        name: string;
        student_id: string | null;
    };
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const [processing, setProcessing] = useState(false);

    const confirm = () =>
        router.delete(destroy(application.application_no).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
            onSuccess: () => onOpenChange(false),
        });

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        Delete application {application.application_no}?
                    </DialogTitle>
                    <DialogDescription>
                        {application.name}'s application, results, addresses and
                        uploaded files will be permanently deleted.
                    </DialogDescription>
                </DialogHeader>
                {application.student_id && (
                    <p className="rounded-md border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700">
                        This applicant is already admitted. Student{' '}
                        <span className="font-mono font-semibold">
                            {application.student_id}
                        </span>{' '}
                        and their login, guardians and results will be deleted
                        too.
                    </p>
                )}
                <p className="text-sm text-muted-foreground">
                    This cannot be undone.
                </p>
                <DialogFooter>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                        disabled={processing}
                    >
                        Cancel
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={confirm}
                        disabled={processing}
                    >
                        {processing && <Spinner />}
                        Delete
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
