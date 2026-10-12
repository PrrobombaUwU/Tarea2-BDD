# Bastian Herrera 202304600-0
# Matias Candia 202404611-K

# SaludUSM – Gestión clínica y administración

PHP + MySQL (PDO). Incluye autenticación, administración de médicos y centros, atenciones clínicas e historial.

## Instalación
1. Para una instalación nueva, importar `ejecutar.sql` en MySQL (crea la BD `saludusm`).
2. Ajustar credenciales en `conexion.php` segun corresponda.
3. Servir la carpeta con Apache/PHP y abrir `login.php`.

## Roles
| Rol | Informacion|
|---|---|---|
| Paciente | Se registra en `nuevopaciente.php` y hace login con credenciales en `login.php`,|
| Médico | Lo crea el Administrador en `admin_medicos.php` y hace login con credenciales en `login.php`|
| Administrador | Viene un administrador incluido en base de datos, se accede por credenciales en `login.php`|

## Usuarios de prueba 
Administrador
Nombre/correo: admin/admin@admin.cl Contraseña: 12345678 
Paciente
Rut: 20123456-1 Contraseña: 1234abcd (Cualquier paciente sirve)
Medico
Rut: 11111111-1 Contraseña: 1234abcd (Cualquier medico sirve)

Otros usuarios se pueden obtener desde las tablas `medico` y `paciente` en la base de datos (`ejecutar.sql`)

## Atenciones e historial clínico
- El médico consulta sus citas en `paginamedico.php`. Solo puede crear o modificar una atención si la cita está `atendida` y pertenece a ese médico; la base de datos permite como máximo una atención por cita.
- La atención guarda motivo de consulta, observaciones clínicas y la especialidad que tenía asignada el médico al registrarla.
- Diagnósticos CIE-10 y líneas de receta solo se pueden gestionar desde una atención del propio médico. Al eliminar una atención, se eliminan también sus diagnósticos y recetas por eliminacion en cascada.
- El paciente puede consultar únicamente su propio historial desde `paciente.php`; no hay operaciones de edición disponibles para pacientes. El médico puede consultar el historial de pacientes que haya atendido, incluyendo atenciones registradas por otros médicos.
- El historial muestra fecha, especialidad, médico, diagnósticos y recetas. Si se elimina la cuenta de un paciente, en cascada se eliminaran las citas, atenciones, diagnosticos y recetas.

## Médicos y centros (administrador)
- `admin_medicos.php` permite crear, consultar, editar y eliminar médicos, y asignarles entre 1 y 3 especialidades y al menos un centro.
- `admin_centros.php` permite crear, consultar, editar y eliminar centros con código, nombre, comuna y región. El código es único.
- No se puede eliminar un médico con citas asociadas, para preservar el historial.
- No se puede quitar una especialidad a un médico que tenga citas futuras (`programada` o `confirmada`). Como las citas de la versión actual no guardan una especialidad separada, se aplica la regla conservadora de bloquear cualquier retiro de especialidad mientras exista alguna cita futura del médico.
- No se puede quitar a un médico un centro asociado a citas futuras; tampoco eliminar un centro con citas futuras, ni eliminarlo si dejaría a algún médico sin centro. Primero hay que cancelar/reprogramar las citas o reasignar el médico.
- Estas validaciones se aplican en las operaciones del servidor. Las especialidades y los centros retirados no alteran citas históricas.

## Validaciones de registro
- **RUT**: formato `XXXXXXXX-X` (7–8 dígitos, guion, dígito o K), sin puntos; No hay verificacion de digito verificador para facilitar uso del sistema. Único en la BD.
- **Correo**: formato válidado con la funcion FILTER_VALIDATE_EMAIL de php.
- **Campos obligatorios**: RUT, nombre, apellido, correo y contraseña (teléfono opcional).
- **Contraseña** (mínimo definido por nosotros): 4 numeros seguido de 4 letras minusculas.

## Datos de usuario
Todos los roles pueden ver y modificar nombre, apellido, correo, teléfono y contraseña en su respectiva pagina. El RUT no es editable (es el identificador legal). Solo el paciente puede eliminar su cuenta.

## ¿Qué ocurre con las citas al eliminar la cuenta de un paciente?
- Se borran todas las citas del paciente, independiente del estado, ademas, por eliminacion en cascada, se eliminan las atencion, recetas, y diagnosticos asociadas a este paciente.

## Suposiciones y definiciones

-Cada bloque de atencion dura 30 minutos. El horario de atencion es de 09:00 AM a 18:00 Pm (Ultima hora se agenda a las 17:30).
-El sistema prohibe quitar especialidades o centros medicos de un medico que tenga citas agendadas (en esa especialidad o centro medico).
-Al eliminar un centro medico que no tenga citas futuras no atendidas, se eliminara el centro medico, y en cascada, la cita, la atencion, la receta y el diagnostico.
-Al agendar una cita esta se guarda, pero para mostrarla hay que recargar la pagina.
-Se puede agendar una cita en la misma hora que otra, mientras esta ultima tenga el estado cancelada