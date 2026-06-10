import React from "react";

const Th = ({ children, className }) => {
  return (
    <th
      className={`p-3 text-sm font-semibold text-base-content/80 ${className}`}
    >
      {children}
    </th>
  );
};

export default Th;
