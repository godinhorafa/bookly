import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { App } from './App';
import { ProblemError } from './api/problem';
import './index.css';

const queryClient = new QueryClient({
  defaultOptions: {
    // Don't retry what the server rejected on purpose (4xx); do retry outages.
    queries: { retry: (count, error) => !(error instanceof ProblemError && error.status < 500) && count < 2 },
  },
});

createRoot(document.getElementById('root') as HTMLElement).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
    </QueryClientProvider>
  </StrictMode>,
);
