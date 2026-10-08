<?php
// Used when a doctor or patient account is deleted, so their sessions, bookings
// and calendar events are removed as well.

require_once __DIR__ . '/calendar.php';

/** Delete one appointment and its calendar events. */
function edoc_delete_appointment(mysqli $db, int $appoid): void
{
    calendar_remove_appointment($db, $appoid);
    $stmt = $db->prepare("DELETE FROM appointment WHERE appoid = ?");
    $stmt->bind_param("i", $appoid);
    $stmt->execute();
}

/** Remove the stored calendar connections for a user. */
function edoc_forget_calendar_connections(mysqli $db, string $email): void
{
    if (calendar_ready($db)) {
        $stmt = $db->prepare("DELETE FROM calendar_connections WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
    }
}

/** Delete a doctor together with their sessions and the bookings for those sessions. */
function edoc_delete_doctor(mysqli $db, int $docid): bool
{
    $stmt = $db->prepare("SELECT docemail FROM doctor WHERE docid = ?");
    $stmt->bind_param("i", $docid);
    $stmt->execute();
    $email = $stmt->get_result()->fetch_assoc()['docemail'] ?? null;
    if ($email === null) {
        return false;
    }

    $docKey = (string)$docid; // schedule.docid is stored as text in the original schema
    $stmt = $db->prepare("SELECT a.appoid FROM appointment a JOIN schedule s ON s.scheduleid = a.scheduleid WHERE s.docid = ?");
    $stmt->bind_param("s", $docKey);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        edoc_delete_appointment($db, (int)$row['appoid']);
    }
    $stmt = $db->prepare("DELETE FROM schedule WHERE docid = ?");
    $stmt->bind_param("s", $docKey);
    $stmt->execute();

    edoc_forget_calendar_connections($db, $email);
    $stmt = $db->prepare("DELETE FROM webuser WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt = $db->prepare("DELETE FROM doctor WHERE docid = ?");
    $stmt->bind_param("i", $docid);
    $stmt->execute();
    return true;
}

/** Delete a patient together with their bookings. */
function edoc_delete_patient(mysqli $db, int $pid): bool
{
    $stmt = $db->prepare("SELECT pemail FROM patient WHERE pid = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    $email = $stmt->get_result()->fetch_assoc()['pemail'] ?? null;
    if ($email === null) {
        return false;
    }

    $stmt = $db->prepare("SELECT appoid FROM appointment WHERE pid = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        edoc_delete_appointment($db, (int)$row['appoid']);
    }

    edoc_forget_calendar_connections($db, $email);
    $stmt = $db->prepare("DELETE FROM webuser WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt = $db->prepare("DELETE FROM patient WHERE pid = ?");
    $stmt->bind_param("i", $pid);
    $stmt->execute();
    return true;
}
