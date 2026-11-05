<?php
/**
 * Lnwthai — Input validation
 * Path: includes/Validator.php
 */
declare(strict_types=1);
defined('LNW_ROOT') || exit;

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $input) {}

    public function value(string $key): string
    {
        $v = $this->input[$key] ?? '';
        return is_string($v) ? trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '') : '';
    }

    public function raw(string $key): string
    {
        $v = $this->input[$key] ?? '';
        return is_string($v) ? $v : '';
    }

    private function add(string $key, string $message): void
    {
        $this->errors[$key] ??= $message;
    }

    public function required(string $key, string $label): self
    {
        if ($this->value($key) === '') {
            $this->add($key, "กรุณากรอก{$label}");
        }
        return $this;
    }

    public function length(string $key, int $min, int $max, string $label): self
    {
        $v = $this->value($key);
        $len = mb_strlen($v);
        if ($v !== '' && ($len < $min || $len > $max)) {
            $this->add($key, "{$label}ต้องมีความยาว {$min}–{$max} ตัวอักษร");
        }
        return $this;
    }

    public function email(string $key): self
    {
        $v = $this->value($key);
        if ($v !== '' && (strlen($v) > 190 || filter_var($v, FILTER_VALIDATE_EMAIL) === false)) {
            $this->add($key, 'รูปแบบอีเมลไม่ถูกต้อง');
        }
        return $this;
    }

    public function username(string $key): self
    {
        $v = $this->value($key);
        if ($v !== '' && preg_match('/^[A-Za-z0-9_.]{3,30}$/', $v) !== 1) {
            $this->add($key, 'ชื่อผู้ใช้ต้องเป็น a-z, 0-9, _ หรือ . ความยาว 3–30 ตัว');
        }
        return $this;
    }

    public function phone(string $key): self
    {
        $v = $this->value($key);
        if ($v !== '' && preg_match('/^[0-9+\-\s]{9,20}$/', $v) !== 1) {
            $this->add($key, 'รูปแบบเบอร์โทรศัพท์ไม่ถูกต้อง');
        }
        return $this;
    }

    public function password(string $key, int $minLength = 10): self
    {
        $p = $this->raw($key);
        if ($p === '') {
            $this->add($key, 'กรุณากรอกรหัสผ่าน');
        } elseif (mb_strlen($p) < $minLength) {
            $this->add($key, "รหัสผ่านต้องมีอย่างน้อย {$minLength} ตัวอักษร");
        } elseif (strlen($p) > 72) {
            $this->add($key, 'รหัสผ่านยาวเกินไป (สูงสุด 72 ไบต์)');
        } elseif (preg_match('/[A-Za-z]/', $p) !== 1 || preg_match('/\d/', $p) !== 1) {
            $this->add($key, 'รหัสผ่านต้องมีทั้งตัวอักษรภาษาอังกฤษและตัวเลข');
        }
        return $this;
    }

    public function same(string $key, string $other, string $message): self
    {
        if ($this->raw($key) !== $this->raw($other)) {
            $this->add($other, $message);
        }
        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        return $this->errors ? (string) reset($this->errors) : null;
    }
}