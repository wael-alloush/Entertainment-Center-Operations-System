import React from "react";

const Lable = ({ children, title, className }) => {
  return (
    <fieldset className={`fieldset ${className}`}>
      <legend className="fieldset-legend text-base-content">{title}</legend>
      {children}
    </fieldset>
  );
};

export default Lable;
