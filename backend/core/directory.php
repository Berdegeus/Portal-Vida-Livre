<?php

declare(strict_types=1);

function normalize_directory_type(?string $type): ?string
{
    $type = strtolower(trim((string) $type));

    if ($type === '') {
        return null;
    }

    return match ($type) {
        'professional', 'profissional', 'especialista', 'especialistas' => 'professional',
        'clinic', 'clinica', 'clínica', 'clinicas', 'clínicas' => 'clinic',
        'support_group', 'grupo_apoio', 'grupo-de-apoio', 'grupos-de-apoio', 'grupos de apoio' => 'support_group',
        default => null,
    };
}

function directory_type_label(string $type): string
{
    return match ($type) {
        'professional' => 'Especialista',
        'clinic' => 'Clínica',
        'support_group' => 'Grupo de apoio',
        default => 'Atendimento',
    };
}

function service_mode_label(string $mode): string
{
    return match ($mode) {
        'online' => 'Online',
        'hibrido' => 'Híbrido',
        default => 'Presencial',
    };
}

function directory_entry_public_data(array $entry): array
{
    return [
        'id' => (int) $entry['id'],
        'slug' => (string) $entry['slug'],
        'entry_type' => (string) $entry['entry_type'],
        'entry_type_label' => directory_type_label((string) $entry['entry_type']),
        'name' => (string) $entry['name'],
        'specialty' => (string) $entry['specialty'],
        'city' => (string) $entry['city'],
        'state' => (string) $entry['state'],
        'location' => trim((string) $entry['city'] . ', ' . (string) $entry['state']),
        'service_mode' => (string) $entry['service_mode'],
        'service_mode_label' => service_mode_label((string) $entry['service_mode']),
        'short_bio' => (string) $entry['short_bio'],
    ];
}

function find_directory_entry_by_id(int $id): ?array
{
    $statement = db()->prepare(
        'SELECT id, slug, entry_type, name, specialty, city, state, service_mode, short_bio
         FROM directory_entries
         WHERE id = :id
           AND is_active = 1
         LIMIT 1'
    );
    $statement->execute(['id' => $id]);
    $entry = $statement->fetch();

    return is_array($entry) ? $entry : null;
}

function directory_home_stats(): array
{
    $statement = db()->query(
        'SELECT
            SUM(CASE WHEN entry_type = "professional" AND is_active = 1 THEN 1 ELSE 0 END) AS specialists_total,
            SUM(CASE WHEN entry_type = "support_group" AND is_active = 1 THEN 1 ELSE 0 END) AS support_groups_total,
            COUNT(DISTINCT CASE WHEN is_active = 1 THEN CONCAT(city, "|", state) END) AS cities_total
         FROM directory_entries'
    );
    $directoryStats = $statement->fetch() ?: [];

    $usersStatement = db()->query('SELECT COUNT(*) FROM users');
    $usersTotal = (int) $usersStatement->fetchColumn();

    return [
        'specialists_total' => (int) ($directoryStats['specialists_total'] ?? 0),
        'support_groups_total' => (int) ($directoryStats['support_groups_total'] ?? 0),
        'transformed_lives_total' => $usersTotal,
        'cities_total' => (int) ($directoryStats['cities_total'] ?? 0),
    ];
}

function directory_metadata(int $limit = 24): array
{
    $specialtiesStatement = db()->prepare(
        'SELECT specialty
         FROM directory_entries
         WHERE is_active = 1
         GROUP BY specialty
         ORDER BY specialty ASC
         LIMIT :limit'
    );
    $specialtiesStatement->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $specialtiesStatement->execute();

    $locationsStatement = db()->prepare(
        'SELECT city, state
         FROM directory_entries
         WHERE is_active = 1
         GROUP BY city, state
         ORDER BY city ASC, state ASC
         LIMIT :limit'
    );
    $locationsStatement->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $locationsStatement->execute();

    $specialties = array_map(
        static fn (array $row): string => (string) $row['specialty'],
        $specialtiesStatement->fetchAll() ?: []
    );

    $locations = array_map(
        static fn (array $row): string => trim((string) $row['city'] . ', ' . (string) $row['state']),
        $locationsStatement->fetchAll() ?: []
    );

    return [
        'specialties' => $specialties,
        'locations' => $locations,
    ];
}

function search_directory(string $specialty = '', string $city = '', ?string $type = null, int $limit = 24): array
{
    $specialty = trim($specialty);
    $city = trim($city);
    $normalizedType = normalize_directory_type($type);
    $conditions = ['is_active = 1'];
    $params = [];

    if ($normalizedType !== null) {
        $conditions[] = 'entry_type = :entry_type';
        $params['entry_type'] = $normalizedType;
    }

    if ($specialty !== '') {
        $conditions[] = '(
            specialty LIKE :specialty_specialty
            OR name LIKE :specialty_name
            OR short_bio LIKE :specialty_bio
        )';
        $specialtyLike = '%' . $specialty . '%';
        $params['specialty_specialty'] = $specialtyLike;
        $params['specialty_name'] = $specialtyLike;
        $params['specialty_bio'] = $specialtyLike;
    }

    if ($city !== '') {
        $conditions[] = '(
            city LIKE :city_name
            OR state LIKE :city_state
            OR CONCAT(city, ", ", state) LIKE :city_location
        )';
        $cityLike = '%' . $city . '%';
        $params['city_name'] = $cityLike;
        $params['city_state'] = $cityLike;
        $params['city_location'] = $cityLike;
    }

    $where = implode(' AND ', $conditions);

    $countStatement = db()->prepare(
        "SELECT COUNT(*) FROM directory_entries WHERE {$where}"
    );
    foreach ($params as $name => $value) {
        $countStatement->bindValue(':' . $name, $value);
    }
    $countStatement->execute();
    $total = (int) $countStatement->fetchColumn();

    $resultsStatement = db()->prepare(
        "SELECT id, slug, entry_type, name, specialty, city, state, service_mode, short_bio
         FROM directory_entries
         WHERE {$where}
         ORDER BY
            CASE entry_type
                WHEN 'professional' THEN 1
                WHEN 'clinic' THEN 2
                ELSE 3
            END,
            name ASC
         LIMIT :limit"
    );
    foreach ($params as $name => $value) {
        $resultsStatement->bindValue(':' . $name, $value);
    }
    $resultsStatement->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $resultsStatement->execute();

    $results = array_map(
        'directory_entry_public_data',
        $resultsStatement->fetchAll() ?: []
    );

    return [
        'total' => $total,
        'filters' => [
            'especialidade' => $specialty,
            'cidade' => $city,
            'tipo' => $normalizedType,
        ],
        'results' => $results,
    ];
}

function list_user_directory_subscriptions(int $userId): array
{
    $statement = db()->prepare(
        'SELECT de.id, de.slug, de.entry_type, de.name, de.specialty, de.city, de.state, de.service_mode, de.short_bio,
                uds.created_at AS subscribed_at
         FROM user_directory_subscriptions uds
         INNER JOIN directory_entries de ON de.id = uds.directory_entry_id
         WHERE uds.user_id = :user_id
           AND de.is_active = 1
         ORDER BY uds.created_at DESC, de.name ASC'
    );
    $statement->execute(['user_id' => $userId]);
    $rows = $statement->fetchAll() ?: [];

    return array_map(
        static function (array $row): array {
            $data = directory_entry_public_data($row);
            $data['subscribed_at'] = $row['subscribed_at'] ?? null;

            return $data;
        },
        $rows
    );
}

function list_user_directory_subscription_ids(int $userId): array
{
    $statement = db()->prepare(
        'SELECT directory_entry_id
         FROM user_directory_subscriptions
         WHERE user_id = :user_id'
    );
    $statement->execute(['user_id' => $userId]);

    return array_map(
        static fn (array $row): int => (int) $row['directory_entry_id'],
        $statement->fetchAll() ?: []
    );
}

function user_is_subscribed_to_directory_entry(int $userId, int $entryId): bool
{
    $statement = db()->prepare(
        'SELECT COUNT(*)
         FROM user_directory_subscriptions
         WHERE user_id = :user_id
           AND directory_entry_id = :directory_entry_id'
    );
    $statement->execute([
        'user_id' => $userId,
        'directory_entry_id' => $entryId,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function subscribe_user_to_directory_entry(int $userId, int $entryId): void
{
    $statement = db()->prepare(
        'INSERT IGNORE INTO user_directory_subscriptions (user_id, directory_entry_id)
         VALUES (:user_id, :directory_entry_id)'
    );
    $statement->execute([
        'user_id' => $userId,
        'directory_entry_id' => $entryId,
    ]);
}

function unsubscribe_user_from_directory_entry(int $userId, int $entryId): void
{
    $statement = db()->prepare(
        'DELETE FROM user_directory_subscriptions
         WHERE user_id = :user_id
           AND directory_entry_id = :directory_entry_id'
    );
    $statement->execute([
        'user_id' => $userId,
        'directory_entry_id' => $entryId,
    ]);
}
