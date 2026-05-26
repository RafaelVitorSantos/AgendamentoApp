"use client";

import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import { useState } from "react";

export function Providers({ children }: { children: React.ReactNode }) {
  const [queryClient] = useState(
    () =>
      new QueryClient({
        defaultOptions: {
          queries: {
            // Dados considerados frescos por 2 min — evita refetch desnecessário
            // ao trocar de aba ou navegar entre páginas do dashboard
            staleTime: 2 * 60 * 1000,
            // Cache mantido por 10 min depois que o componente desmonta
            gcTime: 10 * 60 * 1000,
            retry: 1,
            // Não refetch ao focar a janela — o admin não precisa de dados em
            // tempo real ao voltar de outra aba
            refetchOnWindowFocus: false,
          },
        },
      })
  );

  return (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
}
