<?php

use Illuminate\Support\Facades\Route;

// Las rutas del floor plan se registrarán en la Sesión 5
// cuando implementemos la vinculación operativa

Route::prefix('v1')->middleware(['auth:api'])->group(function () {
    // GET /floor-plans (listar salones)
    // GET /floor-plans/{uuid} (obtener plano completo)
    // POST /floor-plans (crear salón)
    // PUT /floor-plans/{uuid} (actualizar dimensiones)
    // POST /floor-plans/{uuid}/objects (agregar objeto)
    // PUT /floor-plans/{uuid}/objects/{objectUuid} (mover/editar)
    // DELETE /floor-plans/{uuid}/objects/{objectUuid}
    // POST /floor-plans/{uuid}/publish
});
