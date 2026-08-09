namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Modules\Tables\Domain\Exceptions\InvalidTableStatusTransition;
use Throwable;

class Handler extends ExceptionHandler
{
    public function render($request, Throwable $e)
    {
        if ($e instanceof InvalidTableStatusTransition && $request->expectsJson()) {
            return response()->json([
                'error' => 'invalid_status_transition',
                'message' => $e->getMessage(),
            ], 422);
        }

        return parent::render($request, $e);
    }
}
