<?php
// iCloud URL
$icloudUrl = 'https://p224-caldav.icloud.com.cn/published/2/xxxxx'; //change xxxxx to your icloud website address

// Local cache file path
$icloudCacheFile = 'icloud_calendar.ics';
$localFile = 'local_calendar.ics';

// Set update interval to one day（86400秒）
$updateInterval = 86400;

// Update iCloud cache"
if (!file_exists($icloudCacheFile) || (time() - filemtime($icloudCacheFile)) > $updateInterval) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $icloudUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $icsContent = curl_exec($ch);
    curl_close($ch);

    if ($icsContent !== false) {
        file_put_contents($icloudCacheFile, $icsContent);
    }
}

// Handle new event submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $summary = $_POST['summary'];
    $start = $_POST['start'];
    $end = $_POST['end'];

    $event = "BEGIN:VEVENT\r\n";
    $event .= "SUMMARY:$summary\r\n";
    $event .= "DTSTART:" . date('Ymd\THis\Z', strtotime($start)) . "\r\n";
    $event .= "DTEND:" . date('Ymd\THis\Z', strtotime($end)) . "\r\n";
    $event .= "DTSTAMP:" . gmdate('Ymd\THis\Z') . "\r\n";
    $event .= "UID:" . uniqid() . "@yourdomain.com\r\n";
    $event .= "END:VEVENT\r\n";

    if (!file_exists($localFile)) {
        file_put_contents($localFile, "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//yourdomain.com//NONSGML v1.0//EN\r\nEND:VCALENDAR\r\n");
    }

    $localContent = file_get_contents($localFile);
    $localContent = str_replace("END:VCALENDAR\r\n", $event . "END:VCALENDAR\r\n", $localContent);
    file_put_contents($localFile, $localContent);

    echo "Event added successfully.";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ICS Calendar with Event Details</title>
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css' rel='stylesheet' />
    <style>
        .half-width-event {
            width: 50% !important;
            position: absolute !important;
            left: 0;
        }
    </style>
</head>
<body>
    <h1>ICS Calendar Events</h1>
    <div id="calendar" style="max-width: 900px; margin: 0 auto;"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/ical.js/1.4.0/ical.min.js"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js'></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'timeGridWeek',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                slotMinTime: '08:30:00',
                selectable: true,
                selectOverlap: false,
                editable: true,
                selectLongPressDelay: 1, // Shorten touch delay to enhance responsiveness
                longPressDelay: 1,        // Ensure quick response to touch events
                events: function(fetchInfo, successCallback, failureCallback) {
                    var events = [];
                    fetch('<?=$icloudCacheFile?>')
                        .then(response => response.text())
                        .then(icloudData => {
                            var icalData = ICAL.parse(icloudData);
                            var comp = new ICAL.Component(icalData);
                            var vevents = comp.getAllSubcomponents('vevent');
                            vevents.forEach(function(vevent) {
                                var event = new ICAL.Event(vevent);
                                if (event.isRecurring()) {
                                    var expand = new ICAL.RecurExpansion({
                                        component: vevent,
                                        dtstart: event.startDate
                                    });
                                    while (expand.next()) {
                                        var startDate = expand.last.toJSDate();
                                        var endDate = new Date(startDate.getTime() + (event.endDate.toJSDate().getTime() - event.startDate.toJSDate().getTime()));
                                        if (startDate >= fetchInfo.start && startDate <= fetchInfo.end) {
                                            events.push({
                                                title: event.summary,
                                                start: startDate,
                                                end: endDate,
                                                extendedProps: {
                                                    description: event.description
                                                },
                                                className: 'half-width-event'
                                            });
                                        }
                                    }
                                } else {
                                    if (event.startDate.toJSDate() >= fetchInfo.start && event.startDate.toJSDate() <= fetchInfo.end) {
                                        events.push({
                                            title: event.summary,
                                            start: event.startDate.toJSDate(),
                                            end: event.endDate.toJSDate(),
                                            extendedProps: {
                                                description: event.description
                                            },
                                            className: 'half-width-event'
                                        });
                                    }
                                }
                            });
                            return fetch('<?=$localFile?>');
                        })
                        .then(response => response.text())
                        .then(localData => {
                            var localIcalData = ICAL.parse(localData);
                            var localComp = new ICAL.Component(localIcalData);
                            var localEvents = localComp.getAllSubcomponents('vevent');
                            localEvents.forEach(function(vevent) {
                                var event = new ICAL.Event(vevent);
                                if (event.isRecurring()) {
                                    var expand = new ICAL.RecurExpansion({
                                        component: vevent,
                                        dtstart: event.startDate
                                    });
                                    while (expand.next()) {
                                        var startDate = expand.last.toJSDate();
                                        var endDate = new Date(startDate.getTime() + (event.endDate.toJSDate().getTime() - event.startDate.toJSDate().getTime()));
                                        if (startDate >= fetchInfo.start && startDate <= fetchInfo.end) {
                                            events.push({
                                                title: event.summary,
                                                start: startDate,
                                                end: endDate,
                                                extendedProps: {
                                                    description: event.description
                                                },
                                                className: 'half-width-event'
                                            });
                                        }
                                    }
                                } else {
                                    if (event.startDate.toJSDate() >= fetchInfo.start && event.startDate.toJSDate() <= fetchInfo.end) {
                                        events.push({
                                            title: event.summary,
                                            start: event.startDate.toJSDate(),
                                            end: event.endDate.toJSDate(),
                                            extendedProps: {
                                                description: event.description
                                            },
                                            className: 'half-width-event'
                                        });
                                    }
                                }
                            });
                            console.log("All Events:", events);
                            successCallback(events);
                        })
                        .catch(error => {
                            console.error('Error fetching events:', error);
                            failureCallback(error);
                        });
                },
                select: function(info) {
                    var title = prompt('Enter event title:');
                    if (title) {
                        var eventData = {
                            title: title,
                            start: info.startStr,
                            end: info.endStr,
                            className: 'half-width-event'
                        };
                        calendar.addEvent(eventData);
                        fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded'
                            },
                            body: `summary=${encodeURIComponent(title)}&start=${encodeURIComponent(info.startStr)}&end=${encodeURIComponent(info.endStr)}`
                        })
                        .then(response => response.text())
                        .then(data => {
                            console.log(data);
                        })
                        .catch(error => {
                            console.error('Error saving event:', error);
                        });
                    }
                    calendar.unselect();
                },
                eventClick: function(info) {
                    var event = info.event;
                    var content = "Title: " + event.title + "\n" +
                                  "Start: " + event.start.toISOString() + "\n" +
                                  "End: " + (event.end ? event.end.toISOString() : "N/A") + "\n" +
                                  "Description: " + (event.extendedProps.description || "No description");
                    alert(content);
                }
            });

            calendar.render();
        });
    </script>
</body>
</html>
