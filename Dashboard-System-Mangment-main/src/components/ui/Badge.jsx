import React from "react";

const Badge = ({ title, className, style }) => {
  return (
    <div style={style} className={`badge   rounded-full ${className}`}>
      {title}
    </div>
  );
};

export default Badge;
