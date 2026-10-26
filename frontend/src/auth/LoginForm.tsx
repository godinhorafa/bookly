import { ArrowRight, KeyRound, LoaderCircle, Radio, ShieldCheck, Zap } from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { api, unwrap } from '@/api/client';
import { Logo } from '@/components/Logo';
import { ProblemMessage } from '@/components/ProblemMessage';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const DEMO = { email: 'demo@bookly.test', password: 'password' };

const HIGHLIGHTS = [
  { icon: ShieldCheck, text: 'No double bookings, guaranteed by the database' },
  { icon: Zap, text: 'Itemised prices: peak hours, long stays and coupons' },
  { icon: Radio, text: 'Calendars update live when someone else books' },
];

export function LoginForm({ onLoggedIn }: { onLoggedIn: (token: string) => void }) {
  const [email, setEmail] = useState(DEMO.email);
  const [password, setPassword] = useState('');
  const [error, setError] = useState<unknown>(null);
  const [pending, setPending] = useState(false);

  async function submit(event: FormEvent) {
    event.preventDefault();
    setPending(true);
    setError(null);
    try {
      const { token } = await unwrap(api.POST('/tokens', { body: { email, password, device_name: 'dashboard' } }));
      onLoggedIn(token);
    } catch (e) {
      setError(e);
    } finally {
      setPending(false);
    }
  }

  return (
    <main className="grid min-h-svh lg:grid-cols-2">
      <section className="relative hidden overflow-hidden bg-primary p-12 text-primary-foreground lg:flex lg:flex-col lg:justify-between">
        <div aria-hidden className="absolute -top-32 -right-32 size-96 rounded-full bg-white/10 blur-3xl" />
        <div aria-hidden className="absolute -bottom-40 -left-24 size-[28rem] rounded-full bg-black/10 blur-3xl" />
        <Logo className="relative [&>span:first-child]:bg-white/15 [&>span:first-child]:text-primary-foreground" />
        <div className="relative space-y-6">
          <p className="max-w-md text-3xl leading-tight font-semibold tracking-tight">Meeting rooms, courts and halls, booked in seconds.</p>
          <ul className="space-y-3">
            {HIGHLIGHTS.map(({ icon: Icon, text }) => (
              <li key={text} className="flex items-center gap-3 text-primary-foreground/85">
                <span className="grid size-8 place-items-center rounded-lg bg-white/10">
                  <Icon className="size-4" aria-hidden />
                </span>
                {text}
              </li>
            ))}
          </ul>
        </div>
        <p className="relative text-sm text-primary-foreground/70">A portfolio project: Laravel, PostgreSQL, NestJS and React.</p>
      </section>

      <section className="flex items-center justify-center p-6">
        <div className="w-full max-w-sm space-y-6">
          <Logo className="lg:hidden" />
          <Card className="gap-5">
            <CardHeader>
              <CardTitle className="text-xl" asH1>
                Welcome back
              </CardTitle>
              <CardDescription>Log in to book a space and manage your bookings.</CardDescription>
            </CardHeader>
            <CardContent>
              <form onSubmit={submit} aria-label="Log in" className="grid gap-4">
                <div className="grid gap-2">
                  <Label htmlFor="email">E-mail</Label>
                  <Input id="email" type="email" autoComplete="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
                </div>
                <div className="grid gap-2">
                  <Label htmlFor="password">Password</Label>
                  <Input id="password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
                </div>
                <ProblemMessage error={error} title="Couldn’t log you in" />
                <Button type="submit" disabled={pending} className="w-full">
                  {pending ? <LoaderCircle className="animate-spin" aria-hidden /> : null}
                  Log in
                  {pending ? null : <ArrowRight aria-hidden />}
                </Button>
              </form>
            </CardContent>
          </Card>
          <button
            type="button"
            onClick={() => {
              setEmail(DEMO.email);
              setPassword(DEMO.password);
            }}
            className="flex w-full items-center gap-3 rounded-lg border border-dashed bg-card/60 p-3 text-left text-sm transition-colors hover:bg-accent"
          >
            <KeyRound className="size-4 text-primary" aria-hidden />
            <span>
              <span className="font-medium">Use the demo account</span>
              <span className="block text-muted-foreground">
                {DEMO.email} · {DEMO.password}
              </span>
            </span>
          </button>
        </div>
      </section>
    </main>
  );
}
