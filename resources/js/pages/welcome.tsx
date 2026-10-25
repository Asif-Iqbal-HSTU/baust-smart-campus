import { Head, Link, usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowRight,
    BedDouble,
    BookOpen,
    Briefcase,
    Building2,
    Bus,
    ClipboardList,
    FlaskConical,
    GraduationCap,
    HeartPulse,
    Layers,
    Library,
    Presentation,
    ScrollText,
    ShieldCheck,
    Target,
    Users,
    UsersRound,
    Wallet,
    Zap,
} from 'lucide-react';
import LandingAuthCard from '@/components/landing/landing-auth-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { dashboard } from '@/routes';

type Props = {
    canResetPassword: boolean;
    status?: string;
};

type Feature = {
    title: string;
    description: string;
    icon: LucideIcon;
};

const modules: Feature[] = [
    {
        title: 'Admission',
        description:
            'Online applications, admission tests, merit lists and seat confirmation.',
        icon: ClipboardList,
    },
    {
        title: 'Academic',
        description:
            'Curriculum, course registration, class routines and attendance.',
        icon: BookOpen,
    },
    {
        title: 'Exam & Result',
        description:
            'Exam schedules, admit cards, marks entry, GPA/CGPA and transcripts.',
        icon: ScrollText,
    },
    {
        title: 'OBE',
        description:
            'CO–PO mapping, attainment tracking and accreditation-ready reports.',
        icon: Target,
    },
    {
        title: 'Finance & Fees',
        description:
            'Fee structures, online payments, waivers, accounts and budgets.',
        icon: Wallet,
    },
    {
        title: 'HR & Payroll',
        description:
            'Employee records, leave, teaching workload and monthly payroll.',
        icon: Briefcase,
    },
    {
        title: 'Hall',
        description:
            'Seat allocation, hall fees, dining and residential discipline.',
        icon: BedDouble,
    },
    {
        title: 'Library',
        description: 'Catalogue, circulation, fines and digital resources.',
        icon: Library,
    },
    {
        title: 'Transport',
        description: 'Bus routes, passes, vehicles and transport requisitions.',
        icon: Bus,
    },
    {
        title: 'Medical Center',
        description:
            'Health records, prescriptions and medicine stock for campus.',
        icon: HeartPulse,
    },
    {
        title: 'Research',
        description: 'Publications, funded projects and thesis supervision.',
        icon: FlaskConical,
    },
    {
        title: 'Alumni',
        description:
            'Alumni network, career tracking, events and verification.',
        icon: Users,
    },
];

const portals: Feature[] = [
    {
        title: 'Students',
        description:
            'Register courses, pay fees, view results and download documents.',
        icon: GraduationCap,
    },
    {
        title: 'Teachers',
        description:
            'Manage courses, attendance, marks entry and OBE assessments.',
        icon: Presentation,
    },
    {
        title: 'Administration',
        description:
            'Offices and departments run approvals, records and reports.',
        icon: Building2,
    },
    {
        title: 'Guardians',
        description:
            'Follow attendance, results and payments of their students.',
        icon: UsersRound,
    },
];

const highlights: Feature[] = [
    {
        title: 'One secure sign-in',
        description: 'for every office and service',
        icon: ShieldCheck,
    },
    {
        title: 'Module-wise design',
        description: 'each area focused, all connected',
        icon: Layers,
    },
    {
        title: 'Live information',
        description: 'results, dues and reports in real time',
        icon: Zap,
    },
];

function BrandMark() {
    return (
        <div className="flex items-center gap-2.5">
            <div className="flex size-9 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <GraduationCap className="size-5" />
            </div>
            <div className="grid leading-tight">
                <span className="text-sm font-semibold tracking-wide">
                    BAUST CAMPUS
                </span>
                <span className="text-xs text-muted-foreground">
                    University ERP
                </span>
            </div>
        </div>
    );
}

function FeatureCard({ feature }: { feature: Feature }) {
    const Icon = feature.icon;

    return (
        <Card className="gap-4 py-5 transition-shadow hover:shadow-md">
            <CardHeader className="px-5">
                <div className="mb-2 flex size-10 items-center justify-center rounded-lg border bg-muted/50">
                    <Icon className="size-5 text-foreground" />
                </div>
                <CardTitle className="text-base">{feature.title}</CardTitle>
                <CardDescription className="leading-relaxed">
                    {feature.description}
                </CardDescription>
            </CardHeader>
        </Card>
    );
}

export default function Welcome({ canResetPassword, status }: Props) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome" />

            <div className="min-h-screen bg-background text-foreground">
                <header className="sticky top-0 z-40 border-b bg-background/80 backdrop-blur supports-[backdrop-filter]:bg-background/70">
                    <div className="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <BrandMark />

                        <nav className="hidden items-center gap-1 md:flex">
                            <Button variant="ghost" size="sm" asChild>
                                <a href="#modules">Modules</a>
                            </Button>
                            <Button variant="ghost" size="sm" asChild>
                                <a href="#portals">Portals</a>
                            </Button>
                            <Button variant="ghost" size="sm" asChild>
                                <a href="#about">About</a>
                            </Button>
                        </nav>

                        {auth.user ? (
                            <Button size="sm" asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <Button size="sm" asChild>
                                <a href="#access">Sign in</a>
                            </Button>
                        )}
                    </div>
                </header>

                <main>
                    {/* Hero with sign-in */}
                    <section
                        id="access"
                        className="relative scroll-mt-16 overflow-hidden border-b"
                    >
                        <div
                            aria-hidden
                            className="pointer-events-none absolute inset-0 [background-image:linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] [mask-image:radial-gradient(ellipse_at_top_left,black_20%,transparent_70%)] [background-size:48px_48px]"
                        />

                        <div className="relative mx-auto grid max-w-7xl items-center gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1fr_440px] lg:px-8 lg:py-24">
                            <div className="max-w-2xl">
                                <Badge
                                    variant="outline"
                                    className="mb-6 bg-background px-3 py-1"
                                >
                                    Bangladesh Army University of Science and
                                    Technology
                                </Badge>

                                <h1 className="text-4xl font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                    One campus.
                                    <br />
                                    <span className="text-muted-foreground">
                                        One connected platform.
                                    </span>
                                </h1>

                                <p className="mt-6 text-lg leading-relaxed text-muted-foreground">
                                    BAUST CAMPUS brings admission, academics,
                                    examinations, OBE, finance, halls and every
                                    office of the university into a single,
                                    secure system for students, teachers and
                                    administrators.
                                </p>

                                <div className="mt-8 flex flex-wrap gap-3">
                                    <Button size="lg" asChild>
                                        <a href="#modules">
                                            Explore modules
                                            <ArrowRight />
                                        </a>
                                    </Button>
                                    <Button size="lg" variant="outline" asChild>
                                        <a href="#portals">See the portals</a>
                                    </Button>
                                </div>

                                <div className="mt-12 grid gap-6 sm:grid-cols-3">
                                    {highlights.map((item) => (
                                        <div
                                            key={item.title}
                                            className="flex items-start gap-3"
                                        >
                                            <item.icon className="mt-0.5 size-5 shrink-0" />
                                            <div className="grid gap-0.5">
                                                <span className="text-sm font-medium">
                                                    {item.title}
                                                </span>
                                                <span className="text-sm text-muted-foreground">
                                                    {item.description}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <LandingAuthCard
                                canResetPassword={canResetPassword}
                                status={status}
                            />
                        </div>
                    </section>

                    {/* Modules */}
                    <section
                        id="modules"
                        className="mx-auto max-w-7xl scroll-mt-16 px-4 py-20 sm:px-6 lg:px-8"
                    >
                        <div className="mx-auto max-w-2xl text-center">
                            <Badge variant="secondary" className="mb-4">
                                Modules
                            </Badge>
                            <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                                Everything the university runs on
                            </h2>
                            <p className="mt-4 text-muted-foreground">
                                Built module by module. Each one focused on a
                                single office, all of them sharing the same
                                students, courses and records.
                            </p>
                        </div>

                        <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {modules.map((module) => (
                                <FeatureCard
                                    key={module.title}
                                    feature={module}
                                />
                            ))}
                        </div>
                    </section>

                    {/* Portals */}
                    <section
                        id="portals"
                        className="scroll-mt-16 border-y bg-muted/40"
                    >
                        <div className="mx-auto max-w-7xl px-4 py-20 sm:px-6 lg:px-8">
                            <div className="mx-auto max-w-2xl text-center">
                                <Badge variant="secondary" className="mb-4">
                                    Portals
                                </Badge>
                                <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                                    A portal for everyone on campus
                                </h2>
                                <p className="mt-4 text-muted-foreground">
                                    Every person sees exactly what they need,
                                    with one account and role-based access.
                                </p>
                            </div>

                            <div className="mt-12 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {portals.map((portal) => (
                                    <FeatureCard
                                        key={portal.title}
                                        feature={portal}
                                    />
                                ))}
                            </div>
                        </div>
                    </section>

                    {/* About */}
                    <section
                        id="about"
                        className="mx-auto max-w-4xl scroll-mt-16 px-4 py-20 text-center sm:px-6 lg:px-8"
                    >
                        <h2 className="text-3xl font-semibold tracking-tight sm:text-4xl">
                            Built at BAUST, for BAUST
                        </h2>
                        <p className="mt-4 text-lg leading-relaxed text-muted-foreground">
                            BAUST CAMPUS is developed in-house to fit the way
                            our university works, from the admission circular to
                            the convocation stage.
                        </p>
                        {!auth.user && (
                            <Button size="lg" className="mt-8" asChild>
                                <a href="#access">
                                    Sign in to BAUST CAMPUS
                                    <ArrowRight />
                                </a>
                            </Button>
                        )}
                    </section>
                </main>

                <footer className="border-t">
                    <div className="mx-auto flex max-w-7xl flex-col gap-6 px-4 py-10 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8">
                        <BrandMark />
                        <Separator className="md:hidden" />
                        <p className="text-sm text-muted-foreground">
                            &copy; {new Date().getFullYear()} Bangladesh Army
                            University of Science and Technology, Saidpur
                            Cantonment, Nilphamari.
                        </p>
                    </div>
                </footer>
            </div>
        </>
    );
}
