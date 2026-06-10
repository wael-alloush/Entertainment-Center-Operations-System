import React from "react";

const SessionCard = ({ onClick, boxShadowActive, session }) => {
  return (
    <button
      onClick={onClick}
      style={{
        borderColor: session.color,

        boxShadow: boxShadowActive ? `0 0 15px ${session.color}` : "none",
      }}
      className={`min-w-1/4 card bg-base-100 items-center border-2  rounded-lg `}
    >
      <div className="card-body p-4 flex items-center justify-center">
        <h2 className="card-title m-0 text-base-content text-sm font-bold">
          {session.name}
        </h2>
      </div>
      <hr
        style={{
          backgroundColor: session.color,
          color: session.color,
        }}
        className="w-full h-2 bg-primary bogrder-0  mb-2"
      />
    </button>
  );
};

export default SessionCard;
