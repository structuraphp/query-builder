<?php

declare(strict_types=1);

namespace StructuraPhp\QueryBuilder\Service;

use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Pagination\Cursor;
use Illuminate\Validation\ValidationException;
use LogicException;
use StructuraPhp\QueryBuilder\Enum\Type;
use UnexpectedValueException;

final class QueryPaginator
{
    /**
     * @param Builder<Model> $query
     * @param array<string, mixed> $validated
     *
     * @return (
     *     Collection<int, Model>
     *     |CursorPaginator<int, Model>
     *     |LengthAwarePaginator<int, Model>
     * )
     */
    public function paginate(
        Definition $definition,
        Builder $query,
        array $validated,
    ): Collection|CursorPaginator|LengthAwarePaginator {
        $pagination = $definition->pagination;
        $limit = (int) Type::scalar($validated['limit'] ?? $pagination->limit);
        $page = (int) Type::scalar($validated['page'] ?? 1);
        $cursor = $validated['cursor'] ?? null;

        return match ($validated['pagination'] ?? $pagination->default) {
            'none' => $query->get(),
            'cursor' => $this->cursor($definition, $query, $limit, $cursor),
            'offset' => $query
                ->paginate($limit, ['*'], 'page', $page)
                ->withQueryString(),
            default => throw new LogicException('Unvalidated pagination mode.'),
        };
    }

    /**
     * @param Builder<Model> $query
     *
     * @return CursorPaginator<int, Model>
     */
    private function cursor(
        Definition $definition,
        Builder $query,
        int $limit,
        mixed $cursor,
    ): CursorPaginator {
        $keys = [
            $definition->model->getKeyName(),
            $definition->model->getQualifiedKeyName(),
        ];
        $sorted = array_any(
            $query->getQuery()->orders ?? [],
            fn (mixed $order): bool => \is_array($order)
                && \in_array($order['column'] ?? null, $keys, true),
        );
        if (!$sorted) {
            $query->getQuery()->orderBy($keys[1]);
        }

        $cursor = \is_string($cursor) ? $cursor : null;
        // Laravel reads an undecodable cursor as the first page.
        if ($cursor !== null && Cursor::fromEncoded($cursor) === null) {
            throw $this->invalidCursor();
        }

        try {
            return $query
                ->cursorPaginate($limit, ['*'], 'cursor', $cursor)
                ->withQueryString();
        } catch (UnexpectedValueException) {
            throw $this->invalidCursor();
        } catch (QueryException $e) {
            // A tampered cursor value can be rejected by a strict engine
            // (e.g. text against a PostgreSQL bigint).
            throw $cursor === null ? $e : $this->invalidCursor();
        }
    }

    private function invalidCursor(): ValidationException
    {
        return ValidationException::withMessages([
            'cursor' => trans('validation.in', ['attribute' => 'cursor']),
        ]);
    }
}
