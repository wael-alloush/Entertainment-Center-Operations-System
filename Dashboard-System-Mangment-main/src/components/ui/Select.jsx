import React, { Children } from "react";

const select = ({ children, className, defaultValue }) => {
  return (
    <select
      defaultValue={defaultValue}
      className={`select  select-sm md:select-md w-full  bg-base-100 text-base-content border-2 border-base-200 rounded-lg ${className}`}
    >
      {children}
    </select>
  );
};

export default select;
