import { useState, type FormEvent } from 'react';
import { api, unwrap } from '../api/client';
import { ProblemMessage } from '../components/ProblemMessage';

export function LoginForm({ onLoggedIn }: { onLoggedIn: (token: string) => void }) {
  const [email, setEmail] = useState('demo@bookly.test');
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
    <form onSubmit={submit} className="card narrow" aria-label="Log in">
      <h1>Bookly</h1>
      <label>
        E-mail
        <input type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
      </label>
      <label>
        Password
        <input type="password" value={password} onChange={(e) => setPassword(e.target.value)} required />
      </label>
      <ProblemMessage error={error} />
      <button type="submit" disabled={pending}>
        Log in
      </button>
      <p className="hint">Demo account: demo@bookly.test / password</p>
    </form>
  );
}
