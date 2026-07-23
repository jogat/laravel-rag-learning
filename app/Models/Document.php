<?php

namespace App\Models;

use App\Casts\VectorCast;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $content
 * @property array $embedding
 * @property int $id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereContenido($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereEmbedding($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereUpdatedAt($value)
 * @property int $project_id
 * @property int $chunk_index
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereProjectId($value)
 * @property string|null $source
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereChunkIndex($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Document whereSource($value)
 * @mixin \Eloquent
 */

class Document extends Model
{
    protected $fillable = ['content', 'embedding', 'project_id', 'source', 'chunk_index'];

    protected function casts(): array
    {
        return [
            'embedding' => VectorCast::class,
        ];
    }
}
