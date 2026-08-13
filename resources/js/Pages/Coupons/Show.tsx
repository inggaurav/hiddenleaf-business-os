import { createShowPage } from '@/Components/ResourcePage'; export default createShowPage({title:'Coupon',recordKey:'coupon',backPath:'/coupons',editPath:r=>`/coupons/${r.id}/edit`});
