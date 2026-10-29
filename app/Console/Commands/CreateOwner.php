<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class CreateOwner extends Command
{
    protected $signature = 'app:create-owner';
    protected $description = 'Crea la cuenta privada del propietario y carga los clientes iniciales';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->components->error('Ya existe una cuenta. El registro público está deshabilitado.');
            return self::FAILURE;
        }

        $name = trim((string) $this->ask('Tu nombre'));
        $email = trim((string) $this->ask('Tu correo para iniciar sesión'));
        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->components->error('Ingresá un nombre y un correo válido.');
            return self::FAILURE;
        }

        $password = (string) $this->secret('Elegí una contraseña de al menos 12 caracteres');
        if (strlen($password) < 12) {
            $this->components->error('La contraseña debe tener al menos 12 caracteres.');
            return self::FAILURE;
        }
        if ($password !== (string) $this->secret('Repetí la contraseña')) {
            $this->components->error('Las contraseñas no coinciden.');
            return self::FAILURE;
        }

        // Todo o nada: si falla a mitad no queda una cuenta sin clientes que impida volver a correr el comando.
        DB::transaction(function () use ($name, $email, $password) {
            $owner = User::query()->create(['name' => $name, 'email' => $email, 'password' => Hash::make($password)]);
            $examples = [
                ['Cliente 1', 1, 27500], ['Cliente 2', 1, 27500], ['Cliente 3', 1, 27500],
                ['Cliente 4', 1, 27500], ['Cliente 5', 2, 42500],
            ];
            foreach ($examples as [$clientName, $systems, $fee]) {
                $owner->clients()->create(['name' => $clientName, 'systems' => $systems, 'monthly_fee' => $fee, 'due_day' => 10]);
            }
        });

        $this->components->info('Cuenta creada. Se cargaron cinco clientes de ejemplo; podés editar sus nombres y vencimientos.');
        return self::SUCCESS;
    }
}
