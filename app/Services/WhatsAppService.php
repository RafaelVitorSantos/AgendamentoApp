<?php

namespace App\Services;

/**
 * Integração com WhatsApp Business via Evolution API / Z-API.
 * Gracefully no-ops quando a API não está configurada.
 */
class WhatsAppService
{
    private string $apiUrl;
    private string $apiToken;
    private string $instance;
    private bool   $configured;

    public function __construct()
    {
        $this->apiUrl    = rtrim(env('WHATSAPP_API_URL', ''), '/');
        $this->apiToken  = env('WHATSAPP_API_TOKEN', '');
        $this->instance  = env('WHATSAPP_INSTANCE', '');
        $this->configured = !empty($this->apiUrl) && !empty($this->apiToken) && !empty($this->instance);
    }

    /**
     * Envia mensagem de texto.
     *
     * @param string $phone   Número no formato 5511999999999 (sem +, sem espaços)
     * @param string $message Texto da mensagem
     */
    public function sendText(string $phone, string $message): bool
    {
        if (!$this->configured) {
            error_log("WhatsAppService: não configurado. Descartando mensagem para {$phone}.");
            return false;
        }

        $phone = preg_replace('/\D/', '', $phone);
        if (strlen($phone) < 10) {
            return false;
        }

        // Garante prefixo 55 (Brasil)
        if (!str_starts_with($phone, '55')) {
            $phone = '55' . $phone;
        }

        $payload = json_encode([
            'number'  => $phone,
            'text'    => $message,
            'options' => ['delay' => 1200],
        ]);

        $url = "{$this->apiUrl}/message/sendText/{$this->instance}";

        $ctx = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/json\r\nApiKey: {$this->apiToken}",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);

        if ($response === false) {
            error_log("WhatsAppService: falha ao enviar para {$phone}");
            return false;
        }

        $data = json_decode($response, true);
        return isset($data['key']['id']);
    }

    /**
     * Mensagem de confirmação de agendamento.
     */
    public function sendConfirmation(string $phone, array $appointment): bool
    {
        $date  = date('d/m/Y', strtotime($appointment['date']));
        $start = substr($appointment['start_time'], 0, 5);

        $msg = "✅ *Agendamento confirmado!*\n\n"
             . "📅 Data: *{$date}*\n"
             . "🕐 Horário: *{$start}*\n"
             . "💼 Serviço: *{$appointment['service_name']}*\n"
             . "👤 Profissional: *{$appointment['professional_name']}*\n"
             . "📍 Local: *{$appointment['unit_name']}*\n\n"
             . "Até breve! 😊";

        return $this->sendText($phone, $msg);
    }

    /**
     * Lembrete 24h antes.
     */
    public function sendReminder(string $phone, array $appointment): bool
    {
        $date  = date('d/m/Y', strtotime($appointment['date']));
        $start = substr($appointment['start_time'], 0, 5);

        $msg = "⏰ *Lembrete de agendamento!*\n\n"
             . "Seu atendimento é *amanhã*:\n\n"
             . "📅 Data: *{$date}*\n"
             . "🕐 Horário: *{$start}*\n"
             . "💼 Serviço: *{$appointment['service_name']}*\n\n"
             . "Não se esqueça! 😊";

        return $this->sendText($phone, $msg);
    }

    public function isConfigured(): bool
    {
        return $this->configured;
    }
}
