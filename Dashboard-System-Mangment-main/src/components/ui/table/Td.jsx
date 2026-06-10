import React from "react";

const Td = ({ children, className }) => {
  return <td className={`p-3.5 ${className}`}>{children}</td>;
};

export default Td;
