<?php

use Phinx\Migration\AbstractMigration;

/**
 * Stores only the generated filename (a UUID + extension), never the
 * original filename or a full path — the actual file lives under
 * storage/employee-photos/, which .htaccess blocks from direct web
 * access. Photos are served through EmployeeController::showPhoto(),
 * which re-checks tenant/customer ownership on every request.
 */
class AddPhotoToEmployeeInfo extends AbstractMigration
{
    public function change(): void
    {
        $this->table('employee_info')
            ->addColumn('photo', 'string', ['limit' => 255, 'null' => true, 'after' => 'id_number'])
            ->update();
    }
}
