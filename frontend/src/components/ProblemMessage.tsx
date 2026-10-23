import { ProblemError } from '../api/problem';

/** Shows every message of a failed request, e.g. all broken booking rules at once. */
export function ProblemMessage({ error }: { error: unknown }) {
  if (error === null || error === undefined) return null;

  const messages = error instanceof ProblemError ? error.messages() : ['Something went wrong. Please try again.'];

  return (
    <div role="alert" className="problem">
      <ul>
        {messages.map((message) => (
          <li key={message}>{message}</li>
        ))}
      </ul>
    </div>
  );
}
