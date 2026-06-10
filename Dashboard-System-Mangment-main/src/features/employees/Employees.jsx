import React from "react";
import { useState } from "react";

import CardOfEmployee from "./CardOfEmployee";

const startDay = "08:22 م";

const Employees = () => {
  const [employee1, setEmployee1] = useState([
    {
      id: 1,
      name: "وائل",
      checkIn: "00:00",
      checkOut: "00:00",
      late: "00:00",
    },
    {
      id: 2,
      name: "محمد",
      checkIn: "00:00",
      checkOut: "00:00",
      late: "00:00",
    },
    {
      id: 3,
      name: "عبد اللطيف",
      checkIn: "00:00",
      checkOut: "00:00",
      late: "00:00",
    },
  ]);

  //   checkin - startday
  function convertTo24Hour(time12h) {
    const [time, modifier] = time12h.trim().split(" ");
    let [hours, minutes] = time.split(":").map(Number);

    if (modifier === "PM" && hours !== 12) hours += 12;
    if (modifier === "AM" && hours === 12) hours = 0;

    return [hours, minutes];
  }

  function calculateTimeDifference(startTime, checkInTime) {
    try {
      const [startHour, startMinute] = convertTo24Hour(startTime);
      const [checkHour, checkMinute] = convertTo24Hour(checkInTime);

      const startTotalMinutes = startHour * 60 + startMinute;
      const checkTotalMinutes = checkHour * 60 + checkMinute;

      const diffMinutes = checkTotalMinutes - startTotalMinutes;

      return diffMinutes;
    } catch (e) {
      return "وقت غير صالح" + e;
    }
  }

  const handleCheckIn = (id) => {
    const currentTime = new Date()
      .toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      })
      .replace("AM", "ص")
      .replace("PM", "م");

    setEmployee1((prev) =>
      prev.map((emp) =>
        emp.id === id && emp.checkIn === "00:00"
          ? {
              ...emp,
              checkIn: currentTime,
              late: calculateTimeDifference(startDay, currentTime),
            }
          : emp
      )
    );
  };

  const handleCheckOut = (id) => {
    // Logic for check-out
    const currentTime = new Date()
      .toLocaleTimeString([], {
        hour: "2-digit",
        minute: "2-digit",
      })
      .replace("AM", "ص")
      .replace("PM", "م");

    setEmployee1((prev) =>
      prev.map((emp) =>
        emp.id === id && emp.checkOut == "00:00" && emp.checkIn !== "00:00"
          ? { ...emp, checkOut: currentTime }
          : emp
      )
    );
  };

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
      {/* Employee Card */}
      {employee1.map((emp) => (
        <CardOfEmployee
          emp={emp}
          handleCheckOut={handleCheckOut}
          handleCheckIn={handleCheckIn}
        />
      ))}
    </div>
  );
};

export default Employees;
