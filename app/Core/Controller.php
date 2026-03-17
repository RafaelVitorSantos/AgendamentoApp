<?php

namespace App\Core;

/**
 * Controller base com métodos utilitários.
 * Todos os controllers devem estender esta classe.
 */
abstract class Controller
{
    /**
     * Renderiza uma view com dados.
     */
    protected function view(string $view, array $data = []): void
    {
        extract($data);

        $viewPath = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        echo $content;
    }

    /**
     * Renderiza view dentro de um layout.
     */
    protected function render(string $view, array $data = [], string $layout = 'layouts.app'): void
    {
        extract($data);

        $viewPath = BASE_PATH . '/resources/views/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new \RuntimeException("View não encontrada: {$view}");
        }

        ob_start();
        require $viewPath;
        $pageContent = ob_get_clean();

        $layoutPath = BASE_PATH . '/resources/views/' . str_replace('.', '/', $layout) . '.php';
        if (file_exists($layoutPath)) {
            require $layoutPath;
        } else {
            echo $pageContent;
        }
    }

    /**
     * Retorna resposta JSON.
     */
    protected function json(mixed $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Obtém input do request (GET, POST ou JSON body).
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        if (isset($_GET[$key])) {
            return $_GET[$key];
        }

        $jsonInput = $this->jsonBody();
        return $jsonInput[$key] ?? $default;
    }

    /**
     * Obtém todos os inputs do request.
     */
    protected function allInput(): array
    {
        $json = $this->jsonBody();
        return array_merge($_GET, $_POST, $json);
    }

    /**
     * Decodifica body JSON.
     */
    private function jsonBody(): array
    {
        static $parsed = null;
        if ($parsed === null) {
            $raw = file_get_contents('php://input');
            $parsed = json_decode($raw, true) ?? [];
        }
        return $parsed;
    }

    /**
     * Obtém o tenant_id da sessão.
     */
    protected function tenantId(): ?int
    {
        return $_SESSION['tenant_id'] ?? null;
    }

    /**
     * Obtém o user_id da sessão.
     */
    protected function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Verifica se o usuário tem uma permissão.
     * Administrador da empresa (tenant_admin) tem acesso a todos os módulos.
     */
    protected function can(string $permission): bool
    {
        if (($_SESSION['role_name'] ?? '') === 'tenant_admin') {
            return true;
        }
        $permissions = $_SESSION['permissions'] ?? [];
        return in_array($permission, $permissions);
    }

    /**
     * Aborta se não tiver permissão.
     */
    protected function authorize(string $permission): void
    {
        if (!$this->can($permission)) {
            http_response_code(403);
            $this->view('errors.403');
            exit;
        }
    }

    /**
     * Valida inputs com regras básicas.
     * Retorna array de erros (vazio se tudo válido).
     */
    protected function validate(array $rules): array
    {
        $errors = [];
        $inputs = $this->allInput();

        foreach ($rules as $field => $ruleSet) {
            $ruleList = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;
            $value = $inputs[$field] ?? null;

            foreach ($ruleList as $rule) {
                $params = [];
                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $error = $this->checkRule($field, $value, $rule, $params);
                if ($error) {
                    $errors[$field][] = $error;
                    break;
                }
            }
        }

        if (!empty($errors)) {
            $_SESSION['_old_input'] = $inputs;
            $_SESSION['_validation_errors'] = $errors;
        }

        return $errors;
    }

    private function checkRule(string $field, mixed $value, string $rule, array $params): ?string
    {
        return match ($rule) {
            'required' => (empty($value) && $value !== '0') ? "O campo {$field} é obrigatório." : null,
            'email'    => ($value && !filter_var($value, FILTER_VALIDATE_EMAIL)) ? "O campo {$field} deve ser um email válido." : null,
            'min'      => ($value && isset($params[0]) && strlen((string)$value) < (int)$params[0])
                ? "O campo {$field} deve ter no mínimo {$params[0]} caracteres." : null,
            'max'      => ($value && isset($params[0]) && strlen((string)$value) > (int)$params[0])
                ? "O campo {$field} deve ter no máximo {$params[0]} caracteres." : null,
            'numeric'  => ($value && !is_numeric($value)) ? "O campo {$field} deve ser numérico." : null,
            default    => null,
        };
    }
}
