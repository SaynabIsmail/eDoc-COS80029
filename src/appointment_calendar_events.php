<?php
// appointment_calendar_events.php
// Links appointments to their corresponding Google Calendar event ID,
// so cancellations/reschedules can update or delete the right event.

require_once 'connection.php';

// Link an appointment to a Google Calendar event after it's created
function linkAppointmentToEvent($database, $appoid, $googleEventId) {
    $stmt = $database->prepare(
        "INSERT INTO appointment_calendar_events (appoid, google_event_id)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE google_event_id = VALUES(google_event_id)"
    );
    $stmt->bind_param("is", $appoid, $googleEventId);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}

// Get the Google Calendar event ID for a given appointment
// (needed when cancelling/rescheduling, to delete/update the right event)
function getEventForAppointment($database, $appoid) {
    $stmt = $database->prepare(
        "SELECT google_event_id FROM appointment_calendar_events WHERE appoid = ?"
    );
    $stmt->bind_param("i", $appoid);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ? $row['google_event_id'] : null;
}

// Remove the link after the calendar event has been deleted
// (e.g. on appointment cancellation)
function deleteAppointmentEventLink($database, $appoid) {
    $stmt = $database->prepare(
        "DELETE FROM appointment_calendar_events WHERE appoid = ?"
    );
    $stmt->bind_param("i", $appoid);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
?>
