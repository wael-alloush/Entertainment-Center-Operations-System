import React from "react";
import MyButton from "../../components/ui/MyButton";
import Badge from "../../components/ui/Badge";
const CardOfEmployee = ({ handleCheckIn, handleCheckOut, emp }) => {
  return (
    <div
      className="flex flex-col items-center justify-center bg-base-100 text-base-content backdrop-blur-xl rounded-2xl p-6 
       border border-base-300 shadow-lg  "
    >
      <h2 className="text-2xl font-medium mb-4 bg-info w-full text-center py-3 rounded-lg text-primary-content">
        {emp.name}
      </h2>
      {emp.checkIn !== "00:00" && (
        <Badge
          title={
            emp.late > 0
              ? `متأخر ${emp.late} دقيقة`
              : `حضر قبل ${Math.abs(emp.late)} دقيقة`
          }
          className={`border-0 ${emp.late > 0 ? "bg-error" : "bg-success"} ${
            emp.late > 0 && emp.late < 5
              ? "bg-warning text-warning-content"
              : ""
          }`}
        />
      )}

      <div className="mt-4 flex items-baseline space-y-2 space-x-4 w-full">
        <div className="flex flex-col items-center  flex-1">
          <span className="text-lg font-bold p-2 py-1 bg-success/60 w-full text-center rounded-lg">
            {emp.checkIn}
          </span>
        </div>
        <div className="flex flex-col items-center  flex-1">
          <span className="text-lg font-bold p-2 py-1 bg-error/60 w-full text-center  rounded-lg">
            {emp.checkOut}
          </span>
        </div>
      </div>
      <div className="flex items-center space-x-4 mt-4 w-full">
        <MyButton
          disabled={emp.checkIn !== "00:00"}
          onClick={() => handleCheckIn(emp.id)}
          title={"حضور"}
          className={
            "bg-success text-success-content border-success hover:btn-outline hover:bg-transparent hover:text-success flex-1 disabled:text-error-content/50 disabled:cursor-not-allowed"
          }
        />
        <MyButton
          disabled={emp.checkIn === "00:00" || emp.checkOut !== "00:00"}
          onClick={() => handleCheckOut(emp.id)}
          title={"انصراف"}
          className={
            "bg-error text-error-content border-error flex-1 hover:btn-outline hover:bg-transparent hover:text-error disabled:text-error-content/50 disabled:cursor-not-allowed"
          }
        />
      </div>
    </div>
  );
};

export default CardOfEmployee;
