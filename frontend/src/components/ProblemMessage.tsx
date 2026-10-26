import { CircleAlert } from 'lucide-react';
import { ProblemError } from '@/api/problem';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

/** Shows every message of a failed request, e.g. all broken booking rules at once. */
export function ProblemMessage({ error, title }: { error: unknown; title?: string }) {
  if (error === null || error === undefined) return null;

  const messages = error instanceof ProblemError ? error.messages() : ['Something went wrong. Please try again.'];

  return (
    <Alert variant="destructive">
      <CircleAlert />
      <AlertTitle>{title ?? (messages.length > 1 ? `${messages.length} things to fix` : 'That didn’t work')}</AlertTitle>
      <AlertDescription>
        <ul className="list-disc space-y-0.5 pl-4">
          {messages.map((message) => (
            <li key={message}>{message}</li>
          ))}
        </ul>
      </AlertDescription>
    </Alert>
  );
}
