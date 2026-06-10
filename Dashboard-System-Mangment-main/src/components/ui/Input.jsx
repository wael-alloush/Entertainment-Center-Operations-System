import React from "react";

const Input = ({ className, ...props }) => {
  return (
    <input
      {...props}
      className={`w-full input bg-base-100  border-2 border-base-300 focus:border-0 focus:outline-none focus:ring-2 focus:ring-primary/80 rounded-lg ${className}`}
    />
  );
};

export default Input;
