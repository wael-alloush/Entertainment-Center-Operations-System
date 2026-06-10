import React from "react";

const TrBody = ({ children, className }) => {
  return (
    <tr
      className={`rounded-lg ring-1 ring-base-300 hover:ring-primary  transition-all duration-200 ease-in-out ${className}`}
    >
      {children}
    </tr>
  );
};

export default TrBody;
